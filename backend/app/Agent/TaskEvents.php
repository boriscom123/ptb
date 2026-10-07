<?php

namespace App\Agent;

use App\Models\AgentTask;
use Illuminate\Support\Facades\Artisan;
use SergiX44\Nutgram\Nutgram;

/**
 * Применяет события от исполнителя к задаче.
 */
class TaskEvents
{
    public function __construct(private readonly Nutgram $bot) {}

    public function apply(AgentTask $task, array $event): void
    {
        $status = TaskStatus::from($event['status']);

        $task->fill(array_filter([
            'summary' => $event['summary'] ?? null,
            'session_id' => $event['session_id'] ?? null,
            'error' => $event['error'] ?? null,
            'commit' => $event['commit'] ?? null,
            'files' => $event['files'] ?? null,
            'migrations' => $event['migrations'] ?? null,
            'stat' => $event['stat'] ?? null,
        ], fn ($value) => $value !== null));

        $task->status = $status;
        match ($status) {
            TaskStatus::Running => $task->started_at = now(),
            default => $task->finished_at = now(),
        };

        // Новые миграции применяются сразу. Ошибка → ответ 500 → исполнитель откатит коммит.
        if ($status === TaskStatus::Completed && $task->migrations) {
            Artisan::call('migrate', ['--force' => true]);
        }

        $task->save();

        TaskMessage::update($this->bot, $task);
    }
}
