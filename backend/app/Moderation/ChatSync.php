<?php

namespace App\Moderation;

use App\Models\Chat;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat as TelegramChat;
use SergiX44\Nutgram\Telegram\Types\Chat\ChatMember;
use SergiX44\Nutgram\Telegram\Types\Chat\ChatMemberAdministrator;

/**
 * Хранит в БД чаты, где состоит бот, и его права в них.
 */
class ChatSync
{
    public function __construct(private readonly Nutgram $bot) {}

    /**
     * Статус бота в чате изменился (добавили, сделали админом, удалили).
     */
    public function membership(TelegramChat $telegramChat, ChatMember $botMember): Chat
    {
        $chat = Chat::firstOrNew(['telegram_id' => $telegramChat->id]);
        $chat->fill(self::chatAttributes($telegramChat) + self::botAttributes($botMember));
        $chat->save();

        return $chat;
    }

    /**
     * Чат из входящего сообщения: обновляет название, а неизвестный чат регистрирует, запросив права бота.
     */
    public function fromMessage(TelegramChat $telegramChat): Chat
    {
        $chat = Chat::firstWhere('telegram_id', $telegramChat->id);

        if ($chat === null) {
            $botMember = $this->bot->getChatMember($telegramChat->id, $this->botId());

            return $botMember
                ? $this->membership($telegramChat, $botMember)
                : Chat::create(self::chatAttributes($telegramChat) + ['bot_status' => 'member']);
        }

        $chat->fill(self::chatAttributes($telegramChat));
        $chat->isDirty() && $chat->save();

        return $chat;
    }

    /**
     * Группа превратилась в супергруппу — у неё новый ID.
     */
    public function migrate(int $fromTelegramId, int $toTelegramId): void
    {
        if (Chat::where('telegram_id', $toTelegramId)->doesntExist()) {
            Chat::where('telegram_id', $fromTelegramId)->update(['telegram_id' => $toTelegramId, 'type' => 'supergroup']);
        }
    }

    private function botId(): int
    {
        return (int) explode(':', (string) config('nutgram.token'))[0];
    }

    private static function chatAttributes(TelegramChat $chat): array
    {
        $type = $chat->type;

        return [
            'type' => is_string($type) ? $type : $type->value,
            'title' => $chat->title ?? (string) $chat->id,
            'username' => $chat->username,
        ];
    }

    private static function botAttributes(ChatMember $member): array
    {
        $status = $member->status;
        $isAdmin = $member instanceof ChatMemberAdministrator;

        return [
            'bot_status' => is_string($status) ? $status : $status->value,
            'can_delete_messages' => $isAdmin && $member->can_delete_messages,
            'can_restrict_members' => $isAdmin && $member->can_restrict_members,
        ];
    }
}
