<?php

namespace App\Services;

use App\Exceptions\InvalidInitDataException;

/**
 * Проверка initData миниприложения Telegram.
 *
 * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
class InitDataValidator
{
    public function __construct(
        private readonly string $botToken,
        private readonly int $maxAgeSeconds,
    ) {}

    /**
     * @return array{id: int, first_name: string, last_name?: string, username?: string, language_code?: string, is_bot?: bool}
     *
     * @throws InvalidInitDataException
     */
    public function validate(string $initData): array
    {
        parse_str($initData, $fields);

        $hash = $fields['hash'] ?? null;
        unset($fields['hash']);

        if (! is_string($hash) || $fields === []) {
            throw new InvalidInitDataException('Missing hash');
        }

        ksort($fields);
        $dataCheckString = implode("\n", array_map(
            fn ($key, $value) => "$key=$value",
            array_keys($fields),
            $fields,
        ));

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $expectedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($expectedHash, $hash)) {
            throw new InvalidInitDataException('Invalid hash');
        }

        $authDate = (int) ($fields['auth_date'] ?? 0);
        if ($authDate <= 0 || time() - $authDate > $this->maxAgeSeconds) {
            throw new InvalidInitDataException('Expired');
        }

        $user = json_decode($fields['user'] ?? '', true);
        if (! is_array($user) || ! isset($user['id'], $user['first_name'])) {
            throw new InvalidInitDataException('Missing user');
        }

        return $user;
    }
}
