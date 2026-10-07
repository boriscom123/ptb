<?php

use App\Models\User;

return [

    'defaults' => [
        'guard' => 'api',
    ],

    'guards' => [
        // JWT из заголовка Authorization: Bearer <token>, см. AppServiceProvider
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

];
