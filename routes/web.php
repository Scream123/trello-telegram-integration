<?php

use App\Http\Controllers\TrelloController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/trello/callback', [TrelloController::class, 'callback'])->name('trello.callback');

