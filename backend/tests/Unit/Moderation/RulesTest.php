<?php

namespace Tests\Unit\Moderation;

use App\Moderation\MessageText;
use App\Moderation\Rules\LinksRule;
use App\Moderation\Rules\StopWordsRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RulesTest extends TestCase
{
    public static function links(): array
    {
        return [
            'no links' => [[], [], null],
            'forbidden link' => [['https://spam.example/x'], [], 'spam.example'],
            'link without scheme' => [['spam.example'], [], 'spam.example'],
            'allowed domain' => [['https://github.com/a/b'], ['github.com'], null],
            'allowed subdomain' => [['https://docs.github.com'], ['github.com'], null],
            'www is ignored' => [['https://www.github.com'], ['github.com'], null],
            'allowed domain given as url' => [['https://github.com/x'], ['https://GitHub.com/'], null],
            'lookalike domain is not allowed' => [['https://evilgithub.com'], ['github.com'], 'evilgithub.com'],
            'second link forbidden' => [['https://github.com', 'https://t.me/spam'], ['github.com'], 't.me'],
        ];
    }

    #[DataProvider('links')]
    public function test_links_rule(array $urls, array $allowed, ?string $expected): void
    {
        $result = (new LinksRule)->check(new MessageText('text', $urls), ['allowed_domains' => $allowed]);

        $this->assertSame($expected, $result);
    }

    public static function stopWords(): array
    {
        return [
            'whole word' => ['Купи КАЗИНО сейчас', ['казино'], 'казино'],
            'ё equals е' => ['ёлка', ['елка'], 'елка'],
            'part of word does not match' => ['спамер', ['спам'], null],
            'prefix with asterisk' => ['спамеры тут', ['спам*'], 'спам'],
            'phrase' => ['быстрый заработок без вложений', ['заработок без вложений'], 'заработок без вложений'],
            'regex chars are literal' => ['a.b', ['a+b'], null],
            'empty words are ignored' => ['text', ['', ' ', '*'], null],
            'latin' => ['Free CRYPTO here', ['crypto'], 'crypto'],
        ];
    }

    #[DataProvider('stopWords')]
    public function test_stop_words_rule(string $text, array $words, ?string $expected): void
    {
        $result = (new StopWordsRule)->check(new MessageText($text), ['words' => $words]);

        $this->assertSame($expected, $result);
    }
}
