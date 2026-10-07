<?php

namespace App\Moderation;

use SergiX44\Nutgram\Telegram\Properties\MessageEntityType;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

/**
 * Текст сообщения (или подпись к медиа) и ссылки из него — то, что проверяют правила.
 */
class MessageText
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        public readonly string $text,
        public readonly array $urls = [],
    ) {}

    public static function fromMessage(Message $message): self
    {
        $text = $message->getText() ?? '';
        $urls = [];

        foreach ($message->getEntities() ?? [] as $entity) {
            $type = $entity->type instanceof MessageEntityType ? $entity->type : MessageEntityType::tryFrom($entity->type);

            match ($type) {
                MessageEntityType::URL => $urls[] = self::utf16Substr($text, $entity->offset, $entity->length),
                MessageEntityType::TEXT_LINK => $urls[] = (string) $entity->url,
                default => null,
            };
        }

        return new self($text, $urls);
    }

    // Смещения сущностей Telegram заданы в UTF-16 code units
    private static function utf16Substr(string $text, int $offset, int $length): string
    {
        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');

        return mb_convert_encoding(substr($utf16, $offset * 2, $length * 2), 'UTF-8', 'UTF-16LE');
    }
}
