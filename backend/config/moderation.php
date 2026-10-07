<?php

use App\Moderation\Rules\LinksRule;
use App\Moderation\Rules\StopWordsRule;

return [
    // Доступные правила модерации. Новое правило — класс-наследник App\Moderation\Rules\Rule, добавленный сюда.
    'rules' => [
        LinksRule::class,
        StopWordsRule::class,
    ],

    // Сколько секунд кэшировать список администраторов чата
    'admins_cache_ttl' => 600,
];
