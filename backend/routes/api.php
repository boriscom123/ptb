<?php

use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AgentEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

Route::get('/health', function () {
    DB::select('select 1');
    Redis::ping();
    // Загружает routes/telegram.php — ошибка в обработчиках бота тоже будет видна
    app(Nutgram::class);

    return ['status' => 'ok'];
});

Route::post('/telegram/webhook', TelegramWebhookController::class);

Route::post('/agent/events', AgentEventController::class);

Route::post('/auth/telegram', [AuthController::class, 'telegram'])->middleware('throttle:30,1');

Route::middleware('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);

        Route::get('/chats', [ChatController::class, 'index']);
        Route::get('/chats/{chat}', [ChatController::class, 'show']);
        Route::patch('/chats/{chat}', [ChatController::class, 'update']);
        Route::put('/chats/{chat}/rules/{rule}', [ChatController::class, 'updateRule']);
        Route::get('/chats/{chat}/events', [ChatController::class, 'events']);
    });
});
