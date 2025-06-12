<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Repositories\TelegramUserRepository;
use App\Services\TelegramService;
use App\Services\TrelloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $userId = $callbackQuery['from']['id'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';

        if ($data === 'get_report') {
            $telegramUser = $this->telegramUserRepo->findByUserId($userId);

            if ($telegramUser && !empty($telegramUser->id)) {
                $this->showReport($chatId);
            } else {
                $this->sendAuthLink($chatId, $userId);
            }
        }
        $this->telegramService->sendMessage($chatId, 'Fetching report...');
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $userId = $message['from']['id'] ?? null;
        $text = $message['text'] ?? '';

        $this->telegramUserRepo->updateOrCreate([
            'user_id' => $userId,
            'first_name' => $message['from']['first_name'] ?? 'Unknown',
            'last_name' => $message['from']['last_name'] ?? 'Unknown',
            'username' => $message['from']['username'] ?? 'Unknown'
        ]);

        if ($text === '/start') {
            $chatMember = $this->telegramService->getChatMember($chatId, $userId);

            if ($chatMember->status === 'member') {
                $telegramUser = $this->telegramUserRepo->findByUserId($userId);
                if (!$telegramUser || empty($telegramUser->trello_id)) {
                    $this->handleStartCommand($userId, $chatId);
                } else {
                    $this->showReport($chatId);
                }
            } else {
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
            \Log::error('Error fetching Trello boards: ' . $e->getMessage());
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
            \Log::error('Error fetching user tasks for Telegram user ' . $userId . ': ' . $e->getMessage());
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

        if (empty($telegramUser->trello_id)) {
            $this->sendSuccessMessage($chatId);
        } else {
            $this->sendAuthLink($chatId, $userId);
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
            \Log::error('Error sending success message: ' . $e->getMessage());
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
            \Log::error('Error sending error message: ' . $e->getMessage());
        }
    }

    public function generateTrelloAuthUrl(string $userId): string
    {
        $clientId = config('trello.api_key');
        $redirectUri = route('trello.callback', ['user_id' => $userId]);
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
            \Log::error('Error fetching bot info: ' . $e->getMessage());
            return 'DefaultTestPhpBot';
        }
    }

    private function generatePrivateChatLink(string $userId): string
    {
        $uniqueParam = base64_encode($userId); // Generate a unique parameter for the user
        return "https://t.me/{$this->botUsername}?start={$uniqueParam}";
    }

    private function showReport(string $chatId): void
    {
        $members = TelegramUser::with('trelloUser')->get();
        $reportParts = [];

        foreach ($members as $member) {
            $trelloUser = $member->trelloUser;

            if ($trelloUser && $trelloUser->trello_token) {
                try {
                    $tasksInProgress = $this->getTrelloTasksInProgress($trelloUser->trello_token);
                    $reportParts[] = "{$member->first_name} {$member->last_name}: {$tasksInProgress} tasks at work";
                } catch (\Exception $e) {
                    Log::error('Error fetching Trello tasks for user ' . $member->user_id . ': ' . $e->getMessage());
                    $reportParts[] = "{$member->first_name} {$member->last_name}: error fetching tasks";
                }
            } else {
                $reportParts[] = "{$member->first_name} {$member->last_name}: no data, account not linked";
            }
        }

        $report = implode("\n", $reportParts);

        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $report
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending report: ' . $e->getMessage());
        }
    }

    private function getTrelloTasksInProgress(string $trelloToken): string
    {
        $apiKey = config('trello.api_key');
        $boardId = config('trello.id_model');
        $cacheKey = "trello_tasks_in_progress_{$trelloToken}";
        $cacheTTL = 300; // 5 minutes

        $tasksInProgress = Cache::remember($cacheKey, $cacheTTL, function () use ($apiKey, $boardId, $trelloToken) {
            $listsUrl = "https://api.trello.com/1/boards/{$boardId}/lists?cards=open&key={$apiKey}&token={$trelloToken}";
            try {
                $response = Http::get($listsUrl);

                if ($response->successful()) {
                    $lists = $response->json();
                    $tasksInProgress = 0;

                    // Count the number of cards in each list
                    foreach ($lists as $list) {
                        $tasksInProgress += count($list['cards'] ?? []);
                    }

                    return $tasksInProgress;
                } else {
                    \Log::warning('Trello API request failed with status: ' . $response->status());
                    return 0;
                }
            } catch (\Exception $e) {
                \Log::error('Error fetching Trello tasks: ' . $e->getMessage());
                return 0;
            }
        });

        return $tasksInProgress;
    }
}
