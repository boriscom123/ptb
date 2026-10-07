<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use App\Moderation\ChatSync;
use App\Moderation\Moderator;
use SergiX44\Nutgram\Nutgram;

/**
 * Модерация новых и отредактированных сообщений в группах. Нарушившее правило сообщение дальше не обрабатывается.
 */
class ModerateGroupMessages
{
    public function __construct(
        private readonly ChatSync $chats,
        private readonly Moderator $moderator,
    ) {}

    public function __invoke(Nutgram $bot, $next): void
    {
        $update = $bot->update();
        $message = $update?->message ?? $update?->edited_message;

        if ($message === null || ! ($message->chat->isGroup() || $message->chat->isSupergroup())) {
            $next($bot);

            return;
        }

        if ($message->migrate_to_chat_id !== null) {
            $this->chats->migrate($message->chat->id, $message->migrate_to_chat_id);

            return;
        }

        $chat = $this->chats->fromMessage($message->chat);

        if (! $this->moderator->moderate($message, $chat, $bot->get(User::class))) {
            $next($bot);
        }
    }
}
