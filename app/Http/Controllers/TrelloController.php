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
        Log::info('Trello Webhook Received', $request->all());

        $data = $request->all();

        if (isset($data['action']['type']) && $data['action']['type'] === 'updateCard') {
            $cardName = $data['action']['data']['card']['name'];
            $listBefore = $data['action']['data']['listBefore']['name'];
            $listAfter = $data['action']['data']['listAfter']['name'];

            if ($listBefore !== $listAfter) {
                $this->sendToTelegram("Card '{$cardName}' moved from '{$listBefore}' to '{$listAfter}'");
            }
        }
        return response()->json(['status' => 'success']);
    }

    private function sendToTelegram(string $message): void
    {
        $chatId = config('telegram.group_id');

        // Отправка сообщения в Telegram
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
                Log::info('Webhook installed successfully:', $response->json());
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

    public function callback(): View
    {
        return view('trello.callback');
    }

    public function storeUserDataFromToken(Request $request): JsonResponse
    {
        $token = $request->input('token');
        $userId = $request->input('userId');
        Log::info('Response received with userId and token', [
            'userId' => $userId,
            'token' => $token
        ]);
        try {
            // Sending a request to the Trello API
            $response = Http::get('https://api.trello.com/1/members/me', [
                'key' => config('trello.api_key'),
                'token' => $token
            ]);
            Log::info('Response $response', [$response]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Trello API Response Data', $data);

                $trelloUser = TrelloUser::updateOrCreate(
                    ['user_id' => $data['id']],
                    [
                        'full_name' => $data['fullName'] ?? null,
                        'username' => $data['username'] ?? null,
                        'avatar_url' => $data['avatarUrl'] ?? null,
                        'trello_token' => $token
                    ]
                );

                Log::info('Success $trelloUser', [$trelloUser]);

                if ($trelloUser) {
                    TelegramUser::updateOrCreate(
                        ['user_id' => $userId],
                        [
                            'trello_id' => $trelloUser->id
                        ]
                    );
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
