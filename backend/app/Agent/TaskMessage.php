<?php

namespace App\Agent;

use App\Models\AgentTask;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Сообщение со статусом задачи агента в чате администратора.
 */
class TaskMessage
{
    // Лимит Telegram — 4096 символов; оставляем запас под разметку
    private const MAX_PART_LENGTH = 2500;

    public static function text(AgentTask $task): string
    {
        $status = __("agent.status.{$task->status->value}", ['id' => $task->id]);
        $lines = ["<b>$status</b>"];

        if ($task->status->isActive()) {
            $lines[] = '';
            $lines[] = '<i>'.e(Str::limit($task->prompt, 500)).'</i>';
        }

        if ($task->summary && ! $task->status->isActive()) {
            $lines[] = '';
            $lines[] = e(Str::limit($task->summary, self::MAX_PART_LENGTH));
        }

        if ($task->error) {
            $lines[] = '';
            $lines[] = '<pre>'.e(Str::limit($task->error, self::MAX_PART_LENGTH / 2)).'</pre>';
        }

        if ($task->commit) {
            $lines[] = '';
            $lines[] = __('agent.commit', ['commit' => $task->shortCommit(), 'stat' => e((string) $task->stat)]);

            if ($task->migrations) {
                $lines[] = __('agent.migrations', ['count' => count($task->migrations)]);
            }
        }

        if ($task->revert_commit) {
            $lines[] = __('agent.reverted_by', ['commit' => substr($task->revert_commit, 0, 7)]);
        }

        if (in_array($task->status, [TaskStatus::Completed, TaskStatus::Failed], true) && $task->session_id) {
            $lines[] = '';
            $lines[] = '<i>'.__('agent.reply_hint').'</i>';
        }

        return implode("\n", $lines);
    }

    public static function keyboard(AgentTask $task, bool $confirmRevert = false): ?InlineKeyboardMarkup
    {
        if ($task->status->isActive()) {
            return InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make(__('agent.buttons.stop'), callback_data: "agent:stop:{$task->id}"),
            );
        }

        if ($task->status === TaskStatus::Completed && $task->commit) {
            return $confirmRevert
                ? InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make(__('agent.buttons.revert_yes'), callback_data: "agent:revert-yes:{$task->id}"),
                    InlineKeyboardButton::make(__('agent.buttons.revert_no'), callback_data: "agent:revert-no:{$task->id}"),
                )
                : InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make(__('agent.buttons.revert'), callback_data: "agent:revert:{$task->id}"),
                );
        }

        return null;
    }

    /**
     * Обновляет сообщение задачи в Telegram (на языке автора задачи).
     */
    public static function update(Nutgram $bot, AgentTask $task, bool $confirmRevert = false): void
    {
        if ($task->message_id === null) {
            return;
        }

        $previousLocale = App::getLocale();
        App::setLocale($task->user->preferredLocale());

        try {
            $bot->editMessageText(
                text: self::text($task),
                chat_id: $task->chat_id,
                message_id: $task->message_id,
                parse_mode: 'HTML',
                reply_markup: self::keyboard($task, $confirmRevert),
            );
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
