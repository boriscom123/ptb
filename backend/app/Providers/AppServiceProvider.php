<?php

namespace App\Providers;

use App\Models\User;
use App\Moderation\RuleRegistry;
use App\Services\InitDataValidator;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class, fn () => new JwtService(
            secret: (string) config('jwt.secret'),
            ttlMinutes: config('jwt.ttl'),
            algorithm: config('jwt.algorithm'),
            issuer: (string) config('jwt.issuer'),
        ));

        $this->app->singleton(RuleRegistry::class, fn () => new RuleRegistry(config('moderation.rules')));

        $this->app->singleton(InitDataValidator::class, fn () => new InitDataValidator(
            botToken: (string) config('nutgram.token'),
            maxAgeSeconds: config('bot.init_data_ttl'),
        ));
    }

    public function boot(): void
    {
        Auth::viaRequest('jwt', function (Request $request): ?User {
            $token = $request->bearerToken();
            $userId = $token ? app(JwtService::class)->userId($token) : null;

            return $userId ? User::find($userId) : null;
        });
    }
}
