<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook';
    protected $description = 'Set the webhook for the Telegram bot';
    protected $telegram;

    /**
     * @throws TelegramSDKException
     */
    public function __construct()
    {
        parent::__construct();
        $this->telegram = new Api(config('telegram.bot_token'));
    }

    public function handle()
    {
        $this->info('Setting webhook...');

        try {
            $response = $this->telegram->setWebhook(['url' => url('api/telegram/webhook')]);

            if ($response) {
                $this->info('Webhook set successfully.');
            } else {
                $this->error('Failed to set webhook. Response: ' . var_export($response, true));
            }
        } catch (TelegramSDKException $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
