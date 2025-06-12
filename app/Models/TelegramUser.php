<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $user_id
 * @property string|null $full_name
 * @property string|null $username
 * @property string|null $avatar_url
 * @property string|null $trello_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TelegramUser[] $telegramUsers
 */
class TelegramUser extends Model
{
    use HasFactory;

    protected $table = 'telegram_users';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'username',
        'trello_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function trelloUser(): BelongsTo
    {
        return $this->belongsTo(TrelloUser::class, 'trello_id', 'user_id');
    }
}
