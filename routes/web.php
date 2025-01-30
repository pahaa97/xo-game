<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/games', [GameController::class, 'findOrCreateGame']);
Route::post('/games/{game}/move', [GameController::class, 'makeMove']);
