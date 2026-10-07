<?php

namespace App\Jobs;

use App\Models\User;
use App\Telegram\AdminMenu;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use SergiX44\Nutgram\Nutgram;

/**
 * Сообщает пользователю о смене роли и обновляет кнопку админки в его личном чате.
 */
class NotifyRoleChanged implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user) {}

    public function handle(Nutgram $bot): void
    {
        if ($this->user->started_at === null || $this->user->blocked_bot_at !== null) {
            return;
        }

        App::setLocale($this->user->preferredLocale());

        $bot->sendMessage(
            text: __('bot.role_changed', ['role' => $this->user->role->label()]),
            chat_id: $this->user->telegram_id,
            parse_mode: 'HTML',
            reply_markup: $this->user->isAdmin() ? AdminMenu::inlineKeyboard() : null,
        );

        AdminMenu::syncMenuButton($bot, $this->user);
    }
}
