<?php

namespace App\Moderation;

use App\Models\Chat;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\ChatPermissions;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

/**
 * Проверяет сообщение группы включёнными правилами чата и применяет действие к первому нарушению.
 */
class Moderator
{
    public function __construct(
        private readonly Nutgram $bot,
        private readonly RuleRegistry $registry,
        private readonly ChatAdmins $admins,
    ) {}

    /**
     * @return bool true — сообщение нарушило правило и было обработано
     */
    public function moderate(Message $message, Chat $chat, ?User $author): bool
    {
        if (! $chat->moderation_enabled || ! $chat->botIsAdmin() || $this->isExempt($message, $chat)) {
            return false;
        }

        $content = MessageText::fromMessage($message);

        foreach ($chat->rules()->where('enabled', true)->get() as $chatRule) {
            $rule = $this->registry->get($chatRule->rule);
            if ($rule === null) {
                continue;
            }

            $settings = $rule->settings($chatRule->settings);
            $reason = $rule->check($content, $settings);

            if ($reason !== null) {
                $this->punish($message, $chat, $author, $chatRule->rule, $reason, $settings);

                return true;
            }
        }

        return false;
    }

    private function isExempt(Message $message, Chat $chat): bool
    {
        // Автопересылка постов из привязанного канала и анонимные администраторы
        if ($message->is_automatic_forward || $message->sender_chat?->id === $chat->telegram_id) {
            return true;
        }

        // Сообщения от имени канала проверяем, от пользователя — если он не администратор
        if ($message->sender_chat !== null || $message->from === null) {
            return false;
        }

        return $this->admins->isAdmin($chat->telegram_id, $message->from->id);
    }

    private function punish(Message $message, Chat $chat, ?User $author, string $rule, string $reason, array $settings): void
    {
        $action = Action::from($settings['action']);
        $deleted = $this->bot->deleteMessage($chat->telegram_id, $message->message_id) === true;

        if ($action === Action::Mute && $chat->can_restrict_members && $message->sender_chat === null && $message->from) {
            $this->bot->restrictChatMember(
                chat_id: $chat->telegram_id,
                user_id: $message->from->id,
                permissions: ChatPermissions::make(can_send_messages: false),
                until_date: now()->addMinutes($settings['mute_minutes'])->getTimestamp(),
            );
        }

        ModerationEvent::create([
            'chat_id' => $chat->id,
            'user_id' => $author?->id,
            'rule' => $rule,
            'action' => $deleted ? $action->value : 'failed',
            'reason' => Str::limit($reason, 250),
            'message_id' => $message->message_id,
            'message_text' => Str::limit($message->getText() ?? '', 1000),
        ]);
    }
}
