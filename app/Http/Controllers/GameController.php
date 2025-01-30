<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Events\GameUpdated;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function findOrCreateGame(Request $request)
    {
        $playerId = $request->input('player_id');

        // Поиск активной игры игрока
        $existingGame = Game::where(function ($query) use ($playerId) {
            $query->whereJsonContains('players->x', $playerId)
                ->orWhereJsonContains('players->o', $playerId);
        })->whereIn('status', [Game::STATUS_WAITING, Game::STATUS_PLAYING])
            ->first();

        if ($existingGame) {
            return response()->json($existingGame);
        }

        // Поиск игры для подключения
        $game = Game::where('status', Game::STATUS_WAITING)
            ->whereNotNull('players->x')
            ->whereNull('players->o')
            ->where('players->x', '!=', $playerId)
            ->first();

        if ($game) {
            // Обновляем players через массив
            $players = $game->players;
            $players['o'] = $playerId;

            $game->update([
                'players' => $players,
                'status' => Game::STATUS_PLAYING
            ]);
            event(new GameUpdated($game));
        } else {
            // Создаем новую игру
            $game = Game::create([
                'players' => ['x' => $playerId, 'o' => null],
                'current_turn' => 'x',
                'status' => Game::STATUS_WAITING,
                'field' => array_fill(0, 9, null)
            ]);
            event(new GameUpdated($game));
        }

        return response()->json($game);
    }

    public function makeMove(Request $request, Game $game)
    {
        if ($game->status === Game::STATUS_ENDED) {
            return response()->json(['error' => 'Игра завершена'], 400);
        }

        $playerId = $request->input('player_id');
        $position = $request->position;
        $symbol = array_search($playerId, $game->players);

        if ($symbol === false) {
            return response()->json(['error' => 'Игрок не найден'], 403);
        }

        if ($game->current_turn !== $symbol) {
            return response()->json(['error' => 'Не ваш ход'], 400);
        }

        if ($game->field[$position] !== null) {
            return response()->json(['error' => 'Клетка занята'], 400);
        }

        $field = $game->field;
        $field[$position] = $symbol;

        $moveHistory = $game->move_history ?? [];

        // Добавляем текущий ход
        $moveHistory[] = [
            'symbol' => $symbol,
            'position' => $position,
            'timestamp' => now()->toDateTimeString()
        ];

        // Фильтруем ходы текущего игрока
        $playerMoves = array_filter($moveHistory, fn($m) => $m['symbol'] === $symbol);

        // Если у игрока больше 3 ходов - удаляем САМЫЙ СТАРЫЙ его ход
        if (count($playerMoves) > 3) {
            // Находим индекс первого хода этого игрока
            $firstIndex = null;
            foreach ($moveHistory as $i => $move) {
                if ($move['symbol'] === $symbol) {
                    $firstIndex = $i;
                    break;
                }
            }

            if ($firstIndex !== null) {
                // Удаляем из истории и обнуляем клетку
                $removedMove = $moveHistory[$firstIndex];
                unset($moveHistory[$firstIndex]);
                $field[$removedMove['position']] = null;

                // Переиндексируем массив
                $moveHistory = array_values($moveHistory);
            }
        }

        $isWin = $this->checkWin($field, $symbol);

        $game->update([
            'field' => $field,
            'move_history' => $moveHistory,
            'current_turn' => $symbol === 'x' ? 'o' : 'x',
            'status' => $isWin ? Game::STATUS_ENDED : $game->status,
            'winner' => $isWin ? $symbol : null
        ]);

        event(new GameUpdated($game));

        return response()->json($game);
    }

    private function checkWin($field, $symbol)
    {
        $winCombinations = [
            [0, 1, 2], [3, 4, 5], [6, 7, 8],
            [0, 3, 6], [1, 4, 7], [2, 5, 8],
            [0, 4, 8], [2, 4, 6]
        ];

        foreach ($winCombinations as $combination) {
            if (
                $field[$combination[0]] === $symbol &&
                $field[$combination[1]] === $symbol &&
                $field[$combination[2]] === $symbol
            ) {
                return true;
            }
        }
        return false;
    }
}
