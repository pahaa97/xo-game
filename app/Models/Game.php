<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    const STATUS_WAITING = 'waiting';
    const STATUS_PLAYING = 'playing';
    const STATUS_ENDED = 'ended';

    protected $casts = [
        'field' => 'array',
        'players' => 'array',
        'move_history' => 'array' // Добавляем историю ходов
    ];

    protected $fillable = [
        'players',
        'current_turn',
        'status',
        'field',
        'winner',
        'move_history'
    ];
}
