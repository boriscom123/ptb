<?php

namespace App\Moderation\Rules;

use App\Moderation\MessageText;

/**
 * Запрещает сообщения со стоп-словами. Слово ищется целиком без учёта регистра (ё = е);
 * «*» в конце — совпадение по началу слова (например, «спам*»).
 */
class StopWordsRule extends Rule
{
    public function key(): string
    {
        return 'stop_words';
    }

    protected function ruleFields(): array
    {
        return [
            ['name' => 'words', 'type' => 'list', 'default' => []],
        ];
    }

    public function check(MessageText $message, array $settings): ?string
    {
        $text = self::normalize($message->text);

        foreach ($settings['words'] as $word) {
            $word = self::normalize(trim($word));
            $prefix = str_ends_with($word, '*');
            $word = rtrim($word, '*');

            if ($word === '') {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').($prefix ? '' : '(?![\p{L}\p{N}])').'/u';

            if (preg_match($pattern, $text)) {
                return $word;
            }
        }

        return null;
    }

    private static function normalize(string $text): string
    {
        return str_replace('ё', 'е', mb_strtolower($text));
    }
}
