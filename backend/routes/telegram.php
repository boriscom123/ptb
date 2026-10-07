<?php

/** @var Nutgram $bot */

use App\Telegram\Handlers\HelpCommand;
use App\Telegram\Handlers\PrivateChatMemberHandler;
use App\Telegram\Handlers\StartCommand;
use App\Telegram\Middleware\SyncUser;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Telegram\Properties\UpdateType;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeAllPrivateChats;

/*
| Обработчики Telegram. Команды и описания регистрируются в Telegram через `php artisan bot:setup`.
*/

$bot->middleware(SyncUser::class);

$describe = fn (string $command) => [
    '*' => __("bot.commands.$command", locale: 'en'),
    'ru' => __("bot.commands.$command", locale: 'ru'),
];

// Команды работают только в личном чате с ботом
$privateOnly = function (Nutgram $bot, $next) {
    if ($bot->chat()?->isPrivate()) {
        $next($bot);
    }
};

$bot->onCommand('start', StartCommand::class)
    ->description($describe('start'))
    ->scope(new BotCommandScopeAllPrivateChats)
    ->middleware($privateOnly);

$bot->onCommand('help', HelpCommand::class)
    ->description($describe('help'))
    ->scope(new BotCommandScopeAllPrivateChats)
    ->middleware($privateOnly);

$bot->onMyChatMember(PrivateChatMemberHandler::class);

$bot->fallbackOn(UpdateType::MESSAGE, function (Nutgram $bot) {
    if ($bot->chat()?->isPrivate()) {
        $bot->sendMessage(__('bot.unknown'));
    }
});

$bot->onException(function (Nutgram $bot, Throwable $e) {
    Log::error('Telegram update failed', ['exception' => $e]);

    if ($bot->chat()?->isPrivate()) {
        $bot->sendMessage(__('bot.error'));
    }
});

$bot->onApiError(function (Nutgram $bot, TelegramException $e) {
    Log::warning('Telegram API error', ['code' => $e->getCode(), 'message' => $e->getMessage()]);
});
