<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use App\Services\UserSync;
use Illuminate\Support\Facades\App;
use SergiX44\Nutgram\Nutgram;

/**
 * Сохраняет/обновляет отправителя каждого обновления и выставляет локаль по его языку.
 * Найденная модель доступна в обработчиках через $bot->get(User::class).
 */
class SyncUser
{
    public function __construct(private readonly UserSync $userSync) {}

    public function __invoke(Nutgram $bot, $next): void
    {
        $from = $bot->user();

        if ($from === null || $from->is_bot) {
            App::setLocale(config('app.fallback_locale'));
            $next($bot);

            return;
        }

        $user = $this->userSync->sync($from->id, $from->first_name, $from->last_name, $from->username, $from->language_code);

        App::setLocale($user->preferredLocale());
        $bot->set(User::class, $user);

        $next($bot);
    }
}
