<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class GameUpdated implements ShouldBroadcast
{
    public $game;

    public function __construct($game)
    {
        $this->game = $game;
    }

    public function broadcastOn()
    {
        return new Channel('game.'.$this->game->id);
    }
}
