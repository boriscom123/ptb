<?php

return [
    // Исполнитель задач в контейнере agent (через внутренний адрес Caddy)
    'url' => env('AGENT_URL', 'http://caddy:8081'),

    // Общий секрет: Laravel → agent и agent → /api/agent/events
    'secret' => env('AGENT_SECRET'),

    // Максимальная длина текста задачи
    'max_prompt_length' => 4000,
];
