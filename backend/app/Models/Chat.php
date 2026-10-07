<?php

namespace App\Models;

use Database\Factories\ChatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'telegram_id',
    'type',
    'title',
    'username',
    'bot_status',
    'can_delete_messages',
    'can_restrict_members',
    'moderation_enabled',
])]
class Chat extends Model
{
    /** @use HasFactory<ChatFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'can_delete_messages' => 'boolean',
            'can_restrict_members' => 'boolean',
            'moderation_enabled' => 'boolean',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ChatRule::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ModerationEvent::class);
    }

    public function isGroup(): bool
    {
        return in_array($this->type, ['group', 'supergroup'], true);
    }

    public function botIsAdmin(): bool
    {
        return $this->bot_status === 'administrator';
    }
}
