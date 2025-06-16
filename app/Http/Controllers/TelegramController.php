<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Models\TrelloUser;
use App\Repositories\TelegramUserRepository;
use App\Services\TelegramService;
use App\Services\TrelloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

class TelegramController extends Controller
{
    protected $telegramService;
    protected $trelloService;
    protected $telegramUserRepo;
    protected $telegram;
    protected $botUsername;

    public function __construct(
        TelegramService        $telegramService,
        TrelloService          $trelloService,
        Api                    $telegram,
        TelegramUserRepository $telegramUserRepo
    )
    {
        $this->telegramService = $telegramService;
        $this->trelloService = $trelloService;
        $this->telegram = $telegram;
        $this->telegramUserRepo = $telegramUserRepo;
        $this->botUsername = $this->getBotUsername();
    }

    public function setWebhook(): JsonResponse
    {
        $response = $this->telegramService->setWebhook(url('api/telegram/webhook'));
        return response()->json($response);
    }

    public function handleWebhook(Request $request): void
    {
        $update = $request->all();

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
        if (isset($update['my_chat_member'])) {
            $this->handleMyChatMember($update['my_chat_member']);
        }
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $userId = $callbackQuery['from']['id'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? null;

        if (!$userId || !$chatId || !$callbackQueryId) {
            Log::warning('Missing callbackQueryId/chatId/userId');
            return;
        }
        if ($data === 'get_report') {
            Log::info('Received get_report callback');

            try {
                $this->telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQueryId,
                    'text' => 'Generating a report...',
                    'show_alert' => false
                ]);
            } catch (\Exception $e) {
                Log::error('Error answering callback query: ' . $e->getMessage());
            }

            $telegramUser = $this->telegramUserRepo->findByUserId($userId);

            if ($telegramUser && !empty($telegramUser->id)) {
                $this->showReport($chatId);
            } else {
                $this->sendAuthLink($chatId, $userId);
            }
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $userId = $message['from']['id'] ?? null;
        $text = $message['text'] ?? '';
        $firstName = $message['from']['first_name'] ?? 'Unknown';

        $this->telegramUserRepo->updateOrCreate(
            ['user_id' => $userId],
            [
                'first_name' => $firstName,
                'last_name' => $message['from']['last_name'] ?? 'Unknown',
                'username' => $message['from']['username'] ?? 'Unknown'
            ]
        );

        if ($text === '/start') {
            if ($this->isPrivateChat($message)) {
                $telegramUser = $this->telegramUserRepo->findByUserId($userId);

                if (!$telegramUser || empty($telegramUser->trelloUser) || empty($telegramUser->trelloUser->trello_token)) {
                    $this->handleStartCommand((string)$userId, (string)$chatId);
                } else {
                    $this->sendReportButton($chatId);

                }

            } else {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Hello, {$firstName}! Glad to see you in our bot!",
                    'parse_mode' => 'HTML'
                ]);
                $this->telegramService->sendMessage(
                    $chatId,
                    'Please start a private chat with me by clicking [here](https://t.me/' . $this->botUsername . ').',
                    'Markdown'
                );
            }
        }
    }

    public function sendMessage(string $chatId, string $message): JsonResponse
    {
        try {
            $response = $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
            ]);

            if ($response->getMessageId()) {
                return response()->json(['message' => 'Message sent successfully!']);
            } else {
                return response()->json(['message' => 'Failed to send message.'], 400);
            }
        } catch (TelegramSDKException $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getUserBoards(string $userId): JsonResponse
    {
        try {
            $boards = $this->trelloService->getUserBoards($userId);
            return response()->json($boards);

        } catch (\App\Exceptions\TrelloAccountNotLinkedException $e) {
            return response()->json(['message' => 'Trello account not linked.'], 400);

        } catch (\Exception $e) {
            Log::error('Error fetching Trello boards: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching Trello boards.'], 500);
        }
    }

    public function getUserTasks(string $userId): JsonResponse
    {
        try {
            $tasks = $this->trelloService->getUserTasks($userId);

            if ($tasks) {
                return response()->json($tasks);
            } else {
                return response()->json(['message' => 'Failed to fetch tasks or Trello account not linked.'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching user tasks for Telegram user ' . $userId . ': ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }

    private function handleStartCommand(string $userId, string $chatId): void
    {
        $telegramUser = TelegramUser::where('user_id', $userId)->first();

        if (!$telegramUser) {
            $this->sendErrorMessage($chatId, 'User not found.');
            return;
        }

        $trelloUser = TrelloUser::where('telegram_user_id', $telegramUser->id)->first();

        if (!$trelloUser || empty($trelloUser->trello_id)) {
            $this->sendAuthLink($chatId, $userId);
        } else {
            $this->sendSuccessMessage($chatId);
        }
    }


    private function sendSuccessMessage(string $chatId): void
    {
        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Your Trello account has been successfully linked! You can manage tasks and receive reports.',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => 'Get report', 'callback_data' => 'get_report']]
                    ]
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending success message: ' . $e->getMessage());
        }
    }

    private function sendErrorMessage(string $chatId, string $message): void
    {
        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending error message: ' . $e->getMessage());
        }
    }

    public function generateTrelloAuthUrl(string $telegramUserId): string
    {
        $clientId = config('trello.api_key');

        $redirectUri = route('trello.callback', ['telegram_user_id' => $telegramUserId]);

        $scope = 'read,write';

        $authUrl = "https://trello.com/1/authorize?response_type=code&key={$clientId}&redirect_uri=" . urlencode($redirectUri) . "&scope={$scope}";

        return $authUrl;
    }


    public function sendAuthLink(string $chatId, string $userId): void
    {
        $authUrl = $this->generateTrelloAuthUrl($userId);
        $text = 'Your Trello account is not linked. Please link your Trello account to your Telegram by clicking the following link: <a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '">Login to Trello</a>.';

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    private function getBotUsername(): string
    {
        try {
            $response = $this->telegram->getMe();
            $botInfo = $response->getRawResponse();

            return $botInfo['username'] ?? 'DefaultTestPhpBot';
        } catch (TelegramSDKException $e) {
            Log::error('Error fetching bot info: ' . $e->getMessage());
            return 'DefaultTestPhpBot';
        }
    }

    private function generatePrivateChatLink(string $userId): string
    {
        $uniqueParam = base64_encode($userId);
        return "https://t.me/{$this->botUsername}?start={$uniqueParam}";
    }

    private function showReport(int $chatId): void
    {
        $members = TelegramUser::with('trelloUser')->get();
        $reportParts = [];

        foreach ($members as $member) {
            $trelloUser = $member->trelloUser;

            if ($trelloUser && $trelloUser->trello_token) {
                try {
                    $stats = $this->getTrelloTaskStats($trelloUser->trello_token);

                    $reportParts[] = "{$member->first_name} {$member->last_name}:\n" .
                        "In Progress: {$stats['in_progress']}\n" .
                        "Done: {$stats['done']}";
                } catch (\Exception $e) {
                    Log::error('Error fetching Trello tasks for user ' . $member->user_id . ': ' . $e->getMessage());
                    $reportParts[] = "{$member->first_name} {$member->last_name}: error fetching tasks";
                }
            } else {
                $reportParts[] = "{$member->first_name} {$member->last_name}: no data, account not linked";
            }
        }

        $report = implode("\n\n", $reportParts);

        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $report
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending report: ' . $e->getMessage());
        }
    }

    private function getTrelloTaskStats(string $token): array
    {
        $boardId = env('TRELLO_BOARD_ID');
        $key = env('TRELLO_API_KEY');

        $cardsResponse = Http::get("https://api.trello.com/1/boards/{$boardId}/cards?key={$key}&token={$token}");

        if (!$cardsResponse->successful()) {
            throw new \Exception('Failed to fetch Trello cards');
        }

        $cards = $cardsResponse->json();

        $listsResponse = Http::get("https://api.trello.com/1/boards/{$boardId}/lists?key={$key}&token={$token}");

        if (!$listsResponse->successful()) {
            throw new \Exception('Failed to fetch Trello lists');
        }

        $lists = collect($listsResponse->json())
            ->pluck('name', 'id')
            ->map(fn($name) => strtolower($name))
            ->toArray();

        $inProgress = 0;
        $done = 0;

        foreach ($cards as $card) {
            $listId = $card['idList'];
            $listName = $lists[$listId] ?? 'unknown';

            if ($listName === 'inprogress') {
                $inProgress++;
            } elseif ($listName === 'done') {
                $done++;
            }
        }

        return [
            'in_progress' => $inProgress,
            'done' => $done,
        ];
    }


    private function handleMyChatMember(array $myChatMemberUpdate): void
    {
        $chatId = $myChatMemberUpdate['chat']['id'] ?? null;
        $newStatus = $myChatMemberUpdate['new_chat_member']['status'] ?? null;

        if ($newStatus === 'member') {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Hi! Click the button below to get started.',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => 'Start', 'callback_data' => 'start_command']]
                    ]
                ])
            ]);
        }
    }

    private function isPrivateChat(array $message): bool
    {
        return ($message['chat']['type'] ?? null) === 'private';
    }

    private function sendReportButton(int $chatId): void
    {
        try {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Get report', 'callback_data' => 'get_report']
                    ]
                ]
            ];

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Your Trello account has been successfully linked! You can now receive task reports.',
                'reply_markup' => json_encode($keyboard),
                'parse_mode' => 'HTML'
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending success message: ' . $e->getMessage());
        }
    }

}
