<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Moderation\ChatSync;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\ChatMemberBanned;

/**
 * Изменение статуса самого бота: в личном чате — блокировка/разблокировка пользователем,
 * в группах и каналах — добавление, удаление, изменение прав.
 */
class MyChatMemberHandler
{
    public function __construct(private readonly ChatSync $chats) {}

    public function __invoke(Nutgram $bot): void
    {
        $update = $bot->update()->my_chat_member;

        if (! $update->chat->isPrivate()) {
            $this->chats->membership($update->chat, $update->new_chat_member);

            return;
        }

        /** @var User|null $user */
        $user = $bot->get(User::class);

        $user?->forceFill([
            'blocked_bot_at' => $update->new_chat_member instanceof ChatMemberBanned ? now() : null,
        ])->save();
    }
}
