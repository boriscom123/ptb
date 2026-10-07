<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Язык ответов API: по языку Telegram авторизованного пользователя, иначе по Accept-Language.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $languageCode = $request->user()?->language_code
            ?? $request->getPreferredLanguage(config('bot.locales'));

        App::setLocale(User::localeFor($languageCode));

        return $next($request);
    }
}
