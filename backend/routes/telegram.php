<?php

/** @var Nutgram $bot */

use App\Models\User;
use App\Moderation\ChatAdmins;
use App\Telegram\Handlers\AgentHandlers;
use App\Telegram\Handlers\HelpCommand;
use App\Telegram\Handlers\MyChatMemberHandler;
use App\Telegram\Handlers\StartCommand;
use App\Telegram\Middleware\ModerateGroupMessages;
use App\Telegram\Middleware\SyncUser;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Telegram\Properties\UpdateType;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeAllPrivateChats;

/*
| Обработчики Telegram. Команды и описания регистрируются в Telegram через `php artisan bot:setup`.
*/

// Порядок выполнения: SyncUser → ModerateGroupMessages → обработчик
$bot->middleware(SyncUser::class);
$bot->middleware(ModerateGroupMessages::class);

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

$bot->onMyChatMember(MyChatMemberHandler::class);

// Состав администраторов мог измениться — сбрасываем кэш
$bot->onChatMember(fn (Nutgram $bot) => app(ChatAdmins::class)->forget($bot->chatMember()->chat->id));

// Глобальные middleware (в т.ч. модерация) выполняются только при наличии обработчика
$bot->onEditedMessage(fn () => null);

// Кнопки задач AI-агента — только для администраторов
$bot->group(function (Nutgram $bot) {
    $bot->onCallbackQueryData('agent:run:{key}', [AgentHandlers::class, 'run']);
    $bot->onCallbackQueryData('agent:drop:{key}', [AgentHandlers::class, 'drop']);
    $bot->onCallbackQueryData('agent:stop:{id}', [AgentHandlers::class, 'stop']);
    $bot->onCallbackQueryData('agent:revert:{id}', [AgentHandlers::class, 'revert']);
    $bot->onCallbackQueryData('agent:revert-yes:{id}', [AgentHandlers::class, 'revertConfirm']);
    $bot->onCallbackQueryData('agent:revert-no:{id}', [AgentHandlers::class, 'revertCancel']);
})->middleware(function (Nutgram $bot, $next) {
    if ($bot->get(User::class)?->isAdmin()) {
        $next($bot);
    } else {
        $bot->answerCallbackQuery(text: __('api.forbidden'), show_alert: true);
    }
});

// Текст в личном чате: от администратора — задача агенту, от остальных — подсказка
$bot->fallbackOn(UpdateType::MESSAGE, function (Nutgram $bot) {
    if (! $bot->chat()?->isPrivate()) {
        return;
    }

    $text = $bot->message()->text;

    if ($bot->get(User::class)?->isAdmin() && $text !== null && ! str_starts_with($text, '/')) {
        app(AgentHandlers::class)->draft($bot);
    } else {
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
