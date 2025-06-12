<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
class TrelloUser extends Model
{
    use HasFactory;

    protected $table = 'trello_users';

    protected $fillable = [
        'user_id',
        'full_name',
        'username',
        'avatar_url',
        'trello_token',
    ];

    protected $hidden = [
        'trello_token',
    ];

    protected $casts = [
        'trello_token' => 'encrypted',
    ];

    public function telegramUsers(): HasMany
    {
        return $this->hasMany(TelegramUser::class, 'trello_id', 'user_id');
    }
}
