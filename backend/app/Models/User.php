<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'telegram_id',
    'username',
    'first_name',
    'last_name',
    'language_code',
    'role',
    'started_at',
    'blocked_bot_at',
    'last_seen_at',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $attributes = [
        'role' => Role::User->value,
    ];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'role' => Role::class,
            'started_at' => 'datetime',
            'blocked_bot_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Локаль интерфейса по языку Telegram; неподдерживаемые языки — fallback.
     */
    public function preferredLocale(): string
    {
        return self::localeFor($this->language_code);
    }

    public static function localeFor(?string $languageCode): string
    {
        $language = strtolower(substr((string) $languageCode, 0, 2));

        return in_array($language, config('bot.locales'), true) ? $language : config('app.fallback_locale');
    }
}
