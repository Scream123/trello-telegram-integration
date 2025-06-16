<?php

declare(strict_types=1);

namespace App\Services;

use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\ChatMember;

class TelegramService
{
    protected $telegram;

    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }

    public function setWebhook(string $url): array
    {
        try {
            $response = $this->telegram->setWebhook(['url' => $url]);

            if ($response === true) {
                return ['message' => 'Webhook set successfully.'];
            } else {
                return ['message' => 'Failed to set webhook.'];
            }
        } catch (TelegramSDKException $e) {
            Log::error('Telegram webhook error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function sendMessage($chatId, string $message, string $parseMode = 'HTML'): array
    {
        try {
            $response = $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode
            ]);

            if ($response->getMessageId()) {
                return ['message' => 'Message sent successfully!'];
            } else {
                return ['message' => 'Failed to send message.'];
            }
        } catch (TelegramSDKException $e) {
            Log::error('Telegram message sending error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getChatMember($chatId, $userId): ChatMember
    {
        try {
            return $this->telegram->getChatMember([
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);
        } catch (TelegramSDKException $e) {
            Log::error('Error fetching chat member: ' . $e->getMessage());
            throw $e;
        }
    }
}
