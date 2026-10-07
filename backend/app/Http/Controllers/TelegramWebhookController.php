<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use SergiX44\Nutgram\Nutgram;

class TelegramWebhookController
{
    /**
     * Секретный заголовок X-Telegram-Bot-Api-Secret-Token проверяет Nutgram (safe_mode в config/nutgram.php).
     */
    public function __invoke(Nutgram $bot): Response
    {
        $bot->run();

        return response()->noContent();
    }
}
