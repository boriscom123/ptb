<?php

return [
    // Telegram ID пользователя, который всегда получает роль admin
    'admin_telegram_id' => (int) env('ADMIN_TELEGRAM_ID') ?: null,

    // Поддерживаемые языки интерфейса (первые две буквы language_code Telegram)
    'locales' => ['ru', 'en'],

    // Адрес, на который Telegram отправляет обновления
    'webhook_url' => env('APP_URL').'/api/telegram/webhook',

    // Типы обновлений, которые запрашиваются у Telegram
    'allowed_updates' => [
        'message',
        'edited_message',
        'callback_query',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
    ],
];
