<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrelloUser extends Model
{
    use HasFactory;

    protected $table = 'trello_users';

    protected $fillable = [
        'telegram_user_id',
        'trello_id',
        'full_name',
        'username',
        'avatar_url',
        'trello_token',
    ];

    public function telegramUser()
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id', 'id');
    }
}
