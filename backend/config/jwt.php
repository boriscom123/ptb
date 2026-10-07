<?php

return [
    'secret' => env('JWT_SECRET'),

    // Время жизни токена в минутах. После истечения миниприложение заново авторизуется по initData.
    'ttl' => (int) env('JWT_TTL', 60),

    'algorithm' => 'HS256',

    'issuer' => env('APP_URL'),
];
