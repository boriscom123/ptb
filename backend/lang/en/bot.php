<?php

return [
    'start' => "Hi, <b>:name</b>! 👋\n\nYour role: <b>:role</b>.\n\nCommand list — /help",
    'help' => "<b>Commands</b>\n\n/start — get started\n/help — command list",
    'unknown' => "I don't understand this command. Command list — /help",
    'error' => 'Something went wrong. Please try again later.',

    'commands' => [
        'start' => 'Get started',
        'help' => 'Command list',
    ],

    'description' => 'A bot for chat moderation and project administration.',
    'short_description' => 'Chat moderation and project administration',
];
