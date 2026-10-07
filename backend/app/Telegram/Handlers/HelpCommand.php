<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;

class HelpCommand
{
    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(text: __('bot.help'), parse_mode: 'HTML');
    }
}
