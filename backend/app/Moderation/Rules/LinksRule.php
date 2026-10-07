<?php

namespace App\Moderation\Rules;

use App\Moderation\MessageText;

/**
 * Запрещает ссылки, кроме доменов из белого списка (поддомены разрешённого домена тоже разрешены).
 */
class LinksRule extends Rule
{
    public function key(): string
    {
        return 'links';
    }

    protected function ruleFields(): array
    {
        return [
            ['name' => 'allowed_domains', 'type' => 'list', 'default' => []],
        ];
    }

    public function check(MessageText $message, array $settings): ?string
    {
        $allowed = array_map(self::normalizeDomain(...), $settings['allowed_domains']);

        foreach ($message->urls as $url) {
            $host = self::host($url);

            if ($host === null || ! self::isAllowed($host, $allowed)) {
                return $host ?? $url;
            }
        }

        return null;
    }

    private static function host(string $url): ?string
    {
        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = "http://$url";
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? self::normalizeDomain($host) : null;
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = mb_strtolower(trim($domain));
        $domain = preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $domain);

        return preg_replace('~^www\.~', '', rtrim(explode('/', $domain)[0], '.'));
    }

    private static function isAllowed(string $host, array $allowed): bool
    {
        foreach ($allowed as $domain) {
            if ($domain !== '' && ($host === $domain || str_ends_with($host, ".$domain"))) {
                return true;
            }
        }

        return false;
    }
}
