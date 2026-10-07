<?php

namespace App\Moderation;

use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;

/**
 * Администраторы чата (их сообщения не модерируются). Список кэшируется, сбрасывается при изменениях участников.
 */
class ChatAdmins
{
    public function __construct(private readonly Nutgram $bot) {}

    public function isAdmin(int $chatId, int $userId): bool
    {
        $ids = Cache::get(self::key($chatId));

        if ($ids === null) {
            $admins = $this->bot->getChatAdministrators($chatId);

            // Список получить не удалось — не модерируем, чтобы не удалить сообщение администратора
            if ($admins === null) {
                return true;
            }

            $ids = array_map(fn ($member) => $member->user->id, $admins);
            Cache::put(self::key($chatId), $ids, config('moderation.admins_cache_ttl'));
        }

        return in_array($userId, $ids, true);
    }

    public function forget(int $chatId): void
    {
        Cache::forget(self::key($chatId));
    }

    private static function key(int $chatId): string
    {
        return "chat_admins:$chatId";
    }
}
