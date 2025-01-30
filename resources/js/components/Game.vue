<script>
export default {
    data() {
        return {
            game: null,
            timer: 10,
            interval: null,
            searching: false,
            playerId: null,
            echoChannel: null
        };
    },
    mounted() {
        // Генерация и сохранение player_id в localStorage
        this.playerId = localStorage.getItem('player_id');
        if (!this.playerId) {
            this.playerId = crypto.randomUUID();
            localStorage.setItem('player_id', this.playerId);
        }
        this.startGame();
    },
    methods: {
        async handleMove(position) {
            if (
                this.game?.status === 'ended' ||
                !this.isMyTurn ||
                this.game.field[position] !== null
            ) return;

            try {
                await axios.post(`/games/${this.game.id}/move`, {
                    position,
                    player_id: this.playerId
                });
            } catch (error) {
                console.error('Ошибка:', error.response?.data?.error || error.message);
            }
        },
        startNewGame() {
            localStorage.removeItem('player_id');
            this.playerId = crypto.randomUUID();
            localStorage.setItem('player_id', this.playerId);
            this.startGame();
        },
        async startGame() {
            this.searching = true;
            try {
                // Отправляем player_id в теле запроса
                const response = await axios.post('/games', {
                    player_id: this.playerId
                });
                this.game = response.data;
                console.log(this.game);
                this.subscribeToGame();
            } finally {
                this.searching = false;
            }
        },
        subscribeToGame() {
            if (!this.game?.id) return;

            // Отписываемся от предыдущего канала
            if (this.echoChannel) {
                window.Echo.leave(this.echoChannel);
            }

            // Подписываемся на новый канал
            this.echoChannel = `game.${this.game.id}`;
            window.Echo.channel(this.echoChannel)
                .listen('GameUpdated', ({ game }) => {
                    console.log('Игра обновлена:', game);
                    this.game = game;
                    if (this.isMyTurn) this.startTimer();
                });
        },
        async makeMove(position) {
            if (this.game?.field[position] !== null) return;
            // Блокировка ходов при завершенной игре
            if (this.game?.status === 'ended' || !this.isMyTurn) return;

            try {
                await axios.post(`/games/${this.game.id}/move`, {
                    position,
                    player_id: this.playerId
                });
            } catch (error) {
                console.error('Ошибка хода:', error.response?.data?.error || error.message);
            }
        },
        startTimer() {
            clearInterval(this.interval);
            this.timer = 10;
            this.interval = setInterval(() => {
                if (this.timer <= 0) {
                    clearInterval(this.interval);
                    this.skipTurn();
                    return;
                }
                this.timer--;
            }, 1000);
        },
        skipTurn() {
            // Логика пропуска хода
        },
        getPlayerSymbol() {
            return this.game?.players.x === this.playerId ? 'X' : 'O';
        }
    },
    computed: {
        isMyTurn() {
            return this.game?.players[this.game.current_turn] === this.playerId;
        },
        gameStatus() {
            return this.game?.status;
        }
    },
    watch: {
        game(newGame, oldGame) {
            if (newGame?.id !== oldGame?.id) {
                this.subscribeToGame();
            }
        }
    }
};
</script>

<template>
    <div class="game-container">
        <!-- Стартовая кнопка -->
        <div v-if="!game && !searching" class="start-screen">
            <button @click="startGame" class="start-button">
                {{ searching ? 'Поиск...' : 'Начать игру' }}
            </button>
        </div>

        <!-- Поиск соперника с дополнительной информацией -->
        <div v-if="searching" class="searching-overlay">
            <div class="searching-text">🔍 Поиск соперника...</div>
            <div class="searching-subtext">
                Ваш символ: {{ getPlayerSymbol() }} <!-- Добавлено отображение символа -->
            </div>
            <div class="loading-spinner"></div>
        </div>

        <!-- Основной игровой интерфейс -->
        <div v-if="game">
            <!-- Блок статуса игры -->
            <div class="status-container">
                <!-- Результат игры -->
                <div v-if="game.status === 'ended'" class="game-result">
                    <div class="result-message" :class="{
                        'win': game.winner.toUpperCase() === getPlayerSymbol(),
                        'lose': game.winner.toUpperCase() !== getPlayerSymbol()
                    }">
                        {{ game.winner.toUpperCase() === getPlayerSymbol() ? '🏆 ПОБЕДА!' : '💀 ПОРАЖЕНИЕ' }}
                        <!-- Добавлена строка с символом -->
                        <div class="player-symbol">
                            Вы играли за: {{ getPlayerSymbol() }}
                        </div>
                    </div>
                    <button @click="startNewGame" class="restart-button">Новая игра</button>
                </div>

                <!-- Текущий игрок -->
                <div v-else class="player-info">
                    <div :class="['badge', { 'your-turn': isMyTurn }]">
                        Вы играете за: {{ getPlayerSymbol() }}
                    </div>
                </div>
            </div>

            <!-- Игровое поле -->
            <div class="game-grid">
                <div
                    v-for="(cell, index) in game.field"
                    :key="index"
                    @click="handleMove(index)"
                    :class="[
                        'cell',
                        {
                            'x': cell === 'x',
                            'o': cell === 'o',
                            'disabled': game.status === 'ended' || cell !== null
                        }
                    ]"
                >
                    <div v-if="cell" class="symbol">{{ cell }}</div>
                </div>
            </div>

            <!-- Таймер -->
            <div v-if="game.status !== 'ended'" class="game-info">
                <div class="timer">⏳ {{ timer }}s</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Базовый контейнер */
.game-container {
    @apply relative p-8 rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900;
    box-shadow: 0 0 50px rgba(99, 102, 241, 0.2);
}

.player-symbol {
    @apply text-2xl text-gray-300 mt-4;
}

/* Стартовый экран */
.start-screen {
    @apply flex flex-col items-center justify-center h-full;
}

.start-button {
    @apply px-8 py-4 bg-blue-600 text-white rounded-xl text-xl
    font-bold hover:bg-blue-700 transition-all min-w-[200px];
}
.start-button:hover {
    transform: scale(1.1);
}

/* Анимация загрузки */
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Игровое поле */
.game-grid {
    display: grid;
    @apply grid-cols-3 gap-3 w-96 h-96 relative;
}

/* Ячейки */
.cell {
    @apply bg-gray-800 rounded-xl flex items-center justify-center
    cursor-pointer transition-all duration-300;
    aspect-ratio: 1;
}

.cell.x { @apply text-blue-400; }
.cell.o { @apply text-purple-400; }
.cell.disabled {
    @apply cursor-not-allowed opacity-60 pointer-events-none;
}

.symbol {
    @apply text-6xl font-bold;
    text-shadow: 0 0 20px currentColor;
}

/* Информация о статусе */
.game-info {
    @apply mt-8 text-center space-y-4;
}

.timer {
    @apply text-xl text-white font-mono;
}

/* Поиск соперника */
.searching-overlay {
    @apply absolute inset-0 bg-black/90 flex flex-col items-center
    justify-center space-y-4 rounded-2xl z-[100] backdrop-blur-sm;
}

.searching-text {
    @apply text-2xl text-white font-bold animate-pulse;
}

.searching-subtext {
    @apply text-blue-400 text-lg;
}

.loading-spinner {
    @apply w-16 h-16 border-4 border-blue-400 border-t-transparent
    rounded-full animate-spin;
}

/* Панель игрока */
.player-info {
    @apply mb-4 text-center;
}

.badge {
    @apply px-4 py-2 rounded-full bg-gray-700 text-white;
}

.your-turn {
    @apply bg-green-500 animate-pulse;
}

/* Анимации */
.win-animation {
    animation: win-glow 2s infinite;
    position: relative;
    overflow: hidden;
}

.win-animation::after {
    content: '';
    @apply absolute inset-0 bg-gradient-to-r from-yellow-400/20 to-pink-500/20;
    animation: confetti 3s linear infinite;
}

.lose-animation {
    animation: lose-pulse 2s infinite;
}

@keyframes win-glow {
    0%, 100% { box-shadow: 0 0 30px #10b98155; }
    50% { box-shadow: 0 0 50px #10b981aa; }
}

@keyframes lose-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

@keyframes confetti {
    0% { transform: translateY(-100%) rotate(0deg); }
    100% { transform: translateY(100vh) rotate(360deg); }
}

/* Блок результата */
.game-result {
    @apply text-center space-y-4 mb-6;
}

.result-message {
    @apply text-4xl font-bold p-4 rounded-xl;
    text-shadow: 0 0 15px currentColor;
}

.win {
    @apply text-green-400 animate-bounce;
}

.lose {
    @apply text-red-400 animate-pulse;
}

/* Кнопка перезапуска */
.restart-button {
    @apply mt-4 px-6 py-3 bg-green-500 text-white rounded-xl
    text-lg font-bold hover:bg-green-600 transition-all;
}
</style>
