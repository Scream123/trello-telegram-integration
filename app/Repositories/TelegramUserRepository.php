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

    public function updateOrCreate(array $conditions, array $data)
    {
        return TelegramUser::updateOrCreate($conditions, $data);
    }
}
