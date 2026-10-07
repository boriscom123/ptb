<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class StartCommand
{
    public function __invoke(Nutgram $bot): void
    {
        /** @var User $user */
        $user = $bot->get(User::class);

        $user->forceFill(['started_at' => $user->started_at ?? now(), 'blocked_bot_at' => null])->save();

        $bot->sendMessage(
            text: __('bot.start', [
                'name' => e($user->first_name),
                'role' => $user->role->label(),
            ]),
            parse_mode: 'HTML',
        );
    }
}
