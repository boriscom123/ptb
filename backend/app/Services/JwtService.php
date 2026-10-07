<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlMinutes,
        private readonly string $algorithm,
        private readonly string $issuer,
    ) {
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET is missing or too short (min 32 chars).');
        }
    }

    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function issue(User $user): array
    {
        $issuedAt = CarbonImmutable::now();
        $expiresAt = $issuedAt->addMinutes($this->ttlMinutes);

        $token = JWT::encode([
            'iss' => $this->issuer,
            'sub' => (string) $user->getKey(),
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ], $this->secret, $this->algorithm);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Возвращает ID пользователя из валидного токена или null.
     */
    public function userId(string $token): ?int
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (Throwable) {
            return null;
        }

        if (($payload->iss ?? null) !== $this->issuer || ! ctype_digit((string) ($payload->sub ?? ''))) {
            return null;
        }

        return (int) $payload->sub;
    }
}
