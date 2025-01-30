<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('game.{id}', function () {
    return true;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
