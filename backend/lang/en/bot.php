<?php

return [
    'start' => "Hi, <b>:name</b>! 👋\n\nYour role: <b>:role</b>.\n\nCommand list — /help",
    'help' => "<b>Commands</b>\n\n/start — get started\n/help — command list",
    'help_admin' => "\n\n<b>AI agent</b>\nSend a task as a regular message — the agent will change the project and send a report. Reply to the report to refine it.",
    'unknown' => "I don't understand this command. Command list — /help",
    'error' => 'Something went wrong. Please try again later.',
    'role_changed' => 'Your role has been changed: <b>:role</b>.',
    'admin_panel' => 'Admin',
    'open_admin_panel' => '⚙️ Open admin panel',

    'commands' => [
        'start' => 'Get started',
        'help' => 'Command list',
    ],

    'description' => 'A bot for chat moderation and project administration.',
    'short_description' => 'Chat moderation and project administration',
];
