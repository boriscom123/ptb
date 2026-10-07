<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

#[Signature('bot:setup {--drop-pending : Удалить накопившиеся необработанные обновления}')]
#[Description('Регистрирует вебхук, команды и описания бота в Telegram')]
class BotSetup extends Command
{
    public function handle(Nutgram $bot): int
    {
        $url = config('bot.webhook_url');

        $bot->setWebhook(
            url: $url,
            allowed_updates: config('bot.allowed_updates'),
            drop_pending_updates: $this->option('drop-pending'),
            secret_token: md5(config('app.key')),
        );
        $this->components->info("Вебхук: $url");

        $bot->registerMyCommands();
        $this->components->info('Команды зарегистрированы');

        foreach (config('bot.locales') as $locale) {
            // Английский — язык по умолчанию для всех, у кого нет перевода
            $languageCode = $locale === 'en' ? null : $locale;

            $bot->setMyDescription(__('bot.description', locale: $locale), $languageCode);
            $bot->setMyShortDescription(__('bot.short_description', locale: $locale), $languageCode);
        }
        $this->components->info('Описания бота обновлены');

        return self::SUCCESS;
    }
}
