<?php

namespace App\Telegram\Handlers;

use App\Agent\AgentRunner;
use App\Agent\TaskMessage;
use App\Agent\TaskStatus;
use App\Jobs\RevertAgentTask;
use App\Jobs\SubmitAgentTask;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Задачи AI-агенту от администраторов: текст в личном чате → подтверждение → выполнение.
 * Ответ на сообщение с результатом — доработка в той же сессии Claude Code.
 */
class AgentHandlers
{
    private const DRAFT_TTL = 3600;

    public function __construct(private readonly AgentRunner $runner) {}

    public function draft(Nutgram $bot): void
    {
        $message = $bot->message();
        $prompt = trim((string) $message->text);

        if (mb_strlen($prompt) > config('agent.max_prompt_length')) {
            $bot->sendMessage(__('agent.too_long', ['max' => config('agent.max_prompt_length')]));

            return;
        }

        $parent = null;
        if ($replyTo = $message->reply_to_message) {
            $parent = AgentTask::where('chat_id', $message->chat->id)->where('message_id', $replyTo->message_id)->first();

            if ($parent?->status->isActive()) {
                $bot->sendMessage(__('agent.parent_active'));

                return;
            }
        }

        $key = Str::random(16);
        Cache::put("agent_draft:$key", ['prompt' => $prompt, 'parent_id' => $parent?->id], self::DRAFT_TTL);

        $bot->sendMessage(
            text: __($parent ? 'agent.confirm_followup' : 'agent.confirm', ['id' => $parent?->id])."\n\n<i>".e(Str::limit($prompt, 1000)).'</i>',
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make(__('agent.buttons.run'), callback_data: "agent:run:$key"),
                InlineKeyboardButton::make(__('agent.buttons.cancel'), callback_data: "agent:drop:$key"),
            ),
        );
    }

    public function run(Nutgram $bot, string $key): void
    {
        $draft = Cache::pull("agent_draft:$key");

        if ($draft === null) {
            $bot->answerCallbackQuery(text: __('agent.draft_expired'), show_alert: true);
            $bot->editMessageReplyMarkup();

            return;
        }

        /** @var User $user */
        $user = $bot->get(User::class);
        $message = $bot->callbackQuery()->message;

        $task = AgentTask::create([
            'user_id' => $user->id,
            'parent_id' => $draft['parent_id'],
            'prompt' => $draft['prompt'],
            'status' => TaskStatus::Pending,
            'chat_id' => $message->chat->id,
            'message_id' => $message->message_id,
        ]);

        TaskMessage::update($bot, $task);
        SubmitAgentTask::dispatch($task);
        $bot->answerCallbackQuery();
    }

    public function drop(Nutgram $bot, string $key): void
    {
        Cache::forget("agent_draft:$key");

        $bot->editMessageText(__('agent.dropped'));
        $bot->answerCallbackQuery();
    }

    public function stop(Nutgram $bot, string $id): void
    {
        $task = AgentTask::find($id);

        if ($task?->status->isActive()) {
            $this->runner->cancel($task->id);
        }

        $bot->answerCallbackQuery(text: __('agent.stopping'));
    }

    public function revert(Nutgram $bot, string $id): void
    {
        $this->withRevertableTask($bot, $id, fn (AgentTask $task) => TaskMessage::update($bot, $task, confirmRevert: true));
    }

    public function revertCancel(Nutgram $bot, string $id): void
    {
        $this->withRevertableTask($bot, $id, fn (AgentTask $task) => TaskMessage::update($bot, $task));
    }

    public function revertConfirm(Nutgram $bot, string $id): void
    {
        $this->withRevertableTask($bot, $id, function (AgentTask $task) use ($bot) {
            $task->update(['status' => TaskStatus::Reverting]);
            TaskMessage::update($bot, $task);
            RevertAgentTask::dispatch($task);
        });
    }

    private function withRevertableTask(Nutgram $bot, string $id, callable $callback): void
    {
        $task = AgentTask::find($id);

        if ($task?->status === TaskStatus::Completed && $task->commit) {
            $callback($task);
            $bot->answerCallbackQuery();

            return;
        }

        $bot->answerCallbackQuery(text: __('agent.not_revertable'), show_alert: true);
    }
}
