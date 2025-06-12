<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TelegramUser;

class TelegramUserRepository
{
    public function findByUserId($userId)
    {
        return TelegramUser::where('user_id', $userId)->first();
    }

    public function updateOrCreate($data)
    {
        return TelegramUser::updateOrCreate(
            ['user_id' => $data['user_id']],
            $data
        );
    }
}
