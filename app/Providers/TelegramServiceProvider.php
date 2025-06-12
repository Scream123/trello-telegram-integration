<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Api::class, function ($app): Api {
            try {
                return new Api(config('telegram.bot_token'));
            } catch (TelegramSDKException $e) {
                Log::error('Initialization error Telegram API: ' . $e->getMessage());
                throw new RuntimeException(
                    'Failed to initialize Telegram API: ' . $e->getMessage(),
                    previous: $e
                );
            }
        });
    }
}
