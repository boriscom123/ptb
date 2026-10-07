<?php

namespace App\Jobs;

use App\Agent\AgentRunner;
use App\Agent\TaskMessage;
use App\Agent\TaskStatus;
use App\Models\AgentTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Откат задачи: сначала миграции этой задачи (пока их файлы есть в коде), затем git revert в контейнере agent.
 */
class RevertAgentTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public AgentTask $task) {}

    public function handle(AgentRunner $runner, Nutgram $bot): void
    {
        $task = $this->task;
        $paths = array_map(fn (string $path) => Str::after($path, 'backend/'), $task->migrations ?? []);

        try {
            if ($paths !== []) {
                Artisan::call('migrate:reset', ['--path' => $paths, '--force' => true]);
            }

            $response = $runner->revert($task->commit);
            if (! $response->successful()) {
                throw new \RuntimeException($response->json('error', "HTTP {$response->status()}"));
            }

            $task->update(['status' => TaskStatus::Reverted, 'revert_commit' => $response->json('commit'), 'error' => null]);
        } catch (Throwable $e) {
            // Код не откатился — возвращаем миграции обратно
            if ($paths !== []) {
                Artisan::call('migrate', ['--force' => true]);
            }

            $task->update([
                'status' => TaskStatus::Completed,
                'error' => __('agent.revert_failed', ['error' => $e->getMessage()], $task->user->preferredLocale()),
            ]);
        }

        TaskMessage::update($bot, $task);
    }
}
