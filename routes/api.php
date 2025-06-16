<?php

use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TrelloController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/telegram/webhook', [TelegramController::class, 'handleWebhook']);

Route::post('/send-message', [TelegramController::class, 'sendMessage']);
Route::post('/telegram/set-webhook', [TelegramController::class, 'setWebhook']);

Route::post('/trello/webhook', [TrelloController::class, 'handleWebhook']);
Route::post('/trello/set-webhook', [TrelloController::class, 'setWebhook']);
Route::match(['post', 'head'], '/trello/webhook', [TrelloController::class, 'handleWebhook']);
Route::post('/trello/store-user-data', [TrelloController::class, 'storeUserDataFromToken'])->name('trello.store-user-data');

