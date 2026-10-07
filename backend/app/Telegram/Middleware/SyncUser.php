<?php

namespace App\Telegram\Middleware;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\App;
use SergiX44\Nutgram\Nutgram;

/**
 * Сохраняет/обновляет отправителя каждого обновления и выставляет локаль по его языку.
 * Найденная модель доступна в обработчиках через $bot->get(User::class).
 */
class SyncUser
{
    // Как часто обновлять last_seen_at, чтобы не писать в БД на каждое сообщение
    private const LAST_SEEN_THROTTLE_SECONDS = 300;

    public function __invoke(Nutgram $bot, $next): void
    {
        $from = $bot->user();

        if ($from === null || $from->is_bot) {
            App::setLocale(config('app.fallback_locale'));
            $next($bot);

            return;
        }

        $user = User::firstOrNew(['telegram_id' => $from->id]);
        $user->fill([
            'username' => $from->username,
            'first_name' => $from->first_name,
            'last_name' => $from->last_name,
            'language_code' => $from->language_code,
        ]);

        if ($user->last_seen_at === null || $user->last_seen_at->diffInSeconds(now()) >= self::LAST_SEEN_THROTTLE_SECONDS) {
            $user->last_seen_at = now();
        }

        if ($from->id === config('bot.admin_telegram_id')) {
            $user->role = Role::Admin;
        }

        $user->save();

        App::setLocale($user->preferredLocale());
        $bot->set(User::class, $user);

        $next($bot);
    }
}
