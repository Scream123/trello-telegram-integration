<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $chat_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\TelegramUser|null $telegramUser
 */
class UserChatId extends Model
{
    use HasFactory;

    protected $table = 'user_chat_ids';

    protected $fillable = [
        'user_id',
        'chat_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'chat_id' => 'string',
    ];

    /**
     * Get linked Telegram user
     */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id', 'user_id');
    }
}
