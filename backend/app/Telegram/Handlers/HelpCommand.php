<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class HelpCommand
{
    public function __invoke(Nutgram $bot): void
    {
        $text = __('bot.help');

        if ($bot->get(User::class)?->isAdmin()) {
            $text .= __('bot.help_admin');
        }

        $bot->sendMessage(text: $text, parse_mode: 'HTML');
    }
}
