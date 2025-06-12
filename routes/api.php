<?php

use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TrelloController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [TelegramController::class, 'handleWebhook']);

Route::post('/send-message', [TelegramController::class, 'sendMessage']);
Route::post('/telegram/set-webhook', [TelegramController::class, 'setWebhook']);

Route::post('/trello/webhook', [TrelloController::class, 'handleWebhook']);

Route::match(['post', 'head'], '/trello/webhook', [TrelloController::class, 'handleWebhook']);
Route::post('/trello/store-user-data', [TrelloController::class, 'storeUserDataFromToken'])->name('trello.store-user-data');
