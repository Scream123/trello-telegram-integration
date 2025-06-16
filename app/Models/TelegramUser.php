<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TelegramUser extends Model
{
    use HasFactory;

    protected $table = 'telegram_users';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'username',
    ];

    public function trelloUser(): HasOne
    {
        return $this->hasOne(TrelloUser::class, 'telegram_user_id', 'id');
    }
}
