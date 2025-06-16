<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Models\TrelloUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Telegram\Bot\Api;

class TrelloController extends Controller
{
    protected $telegram;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bot_token'));
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        $data = $request->all();

        if (isset($data['action']['type']) && $data['action']['type'] === 'updateCard') {
            $cardName = $data['action']['data']['card']['name'];
            $listBefore = $data['action']['data']['listBefore']['name'];
            $listAfter = $data['action']['data']['listAfter']['name'];
            $allowedLists = ['InProgress', 'Done'];

            if (in_array($listBefore, $allowedLists) && in_array($listAfter, $allowedLists) && $listBefore !== $listAfter) {
                $this->sendToTelegram("Card '{$cardName}' moved from '{$listBefore}' to '{$listAfter}'");
            }
        }
        return response()->json(['status' => 'success']);
    }

    private function sendToTelegram(string $message): void
    {
        $chatId = config('telegram.group_id');
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }

    public function setWebhook(): JsonResponse
    {
        $webhookUrl = config('trello.webhook_url');
        $key = config('trello.api_key');
        $token = config('trello.token');
        $idModel = config('trello.id_model');

        try {
            $response = Http::post("https://api.trello.com/1/webhooks/?key={$key}&token={$token}", [
                'description' => 'MyWebhook',
                'callbackURL' => $webhookUrl,
                'idModel' => $idModel,
                'active' => true,
                'filter' => 'all',
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                Log::error('Installation error Webhook: ' . $response->body());
                return response()->json(['error' => 'Installation error Webhook'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Installation error Webhook: ' . $e->getMessage());
            return response()->json(['error' => 'Installation error Webhook'], 500);
        }
    }

    public function callback(Request $request): View
    {
        return view('trello.callback');
    }

    public function storeUserDataFromToken(Request $request): JsonResponse
    {
        $token = $request->input('token');
        $userId = $request->input('user_id');

        try {
            $telegramUser = TelegramUser::where('user_id', $userId)->first();

            if (!$telegramUser) {
                return response()->json(['success' => false, 'error' => 'Telegram user not found'], 404);
            }

            $response = Http::get('https://api.trello.com/1/members/me', [
                'key' => config('trello.api_key'),
                'token' => $token
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $trelloUser = TrelloUser::updateOrCreate(
                    ['telegram_user_id' => $telegramUser->id],
                    [
                        'trello_id' => $data['id'] ?? null,
                        'full_name' => $data['fullName'] ?? null,
                        'username' => $data['username'] ?? null,
                        'avatar_url' => $data['avatarUrl'] ?? null,
                        'trello_token' => $token
                    ]
                );

                if ($trelloUser) {
                    $telegramUser->update([
                        'trello_id' => $data['id'] ?? null
                    ]);
                }

                return response()->json(['success' => true]);
            } else {
                Log::error('Error from Trello API: ' . $response->body());
                return response()->json(['success' => false, 'error' => 'Error from Trello API'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error storing Trello user data: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
