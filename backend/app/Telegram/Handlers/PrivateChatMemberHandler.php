<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\ChatMemberBanned;

/**
 * Отслеживает, заблокировал ли пользователь бота в личном чате.
 */
class PrivateChatMemberHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $update = $bot->chatMember();

        if ($update === null || ! $update->chat->isPrivate()) {
            return;
        }

        /** @var User|null $user */
        $user = $bot->get(User::class);

        $user?->forceFill([
            'blocked_bot_at' => $update->new_chat_member instanceof ChatMemberBanned ? now() : null,
        ])->save();
    }
}
