<?php

namespace App\Telegram;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonDefault;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonWebApp;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

/**
 * Кнопки открытия админки (миниприложения) — только для администраторов.
 */
class AdminMenu
{
    /**
     * Кнопка меню рядом с полем ввода в личном чате: админка для admin, обычное меню команд для остальных.
     */
    public static function syncMenuButton(Nutgram $bot, User $user): void
    {
        $button = $user->isAdmin()
            ? new MenuButtonWebApp(__('bot.admin_panel', locale: $user->preferredLocale()), self::webApp())
            : new MenuButtonDefault;

        $bot->setChatMenuButton(chat_id: $user->telegram_id, menu_button: $button);
    }

    public static function inlineKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make(text: __('bot.open_admin_panel'), web_app: self::webApp()),
        );
    }

    private static function webApp(): WebAppInfo
    {
        return new WebAppInfo(config('bot.web_app_url'));
    }
}
