<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;

/**
 * Создание/обновление пользователя по данным из Telegram (обновления бота и initData миниприложения).
 */
class UserSync
{
    // Как часто обновлять last_seen_at, чтобы не писать в БД на каждое сообщение
    private const LAST_SEEN_THROTTLE_SECONDS = 300;

    public function sync(int $telegramId, string $firstName, ?string $lastName, ?string $username, ?string $languageCode): User
    {
        $user = User::firstOrNew(['telegram_id' => $telegramId]);
        $user->fill([
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'language_code' => $languageCode,
        ]);

        if ($user->last_seen_at === null || $user->last_seen_at->diffInSeconds(now()) >= self::LAST_SEEN_THROTTLE_SECONDS) {
            $user->last_seen_at = now();
        }

        if ($telegramId === config('bot.admin_telegram_id')) {
            $user->role = Role::Admin;
        }

        $user->save();

        return $user;
    }
}
