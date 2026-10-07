<?php

namespace App\Console\Commands;

use App\Agent\AgentRunner;
use App\Agent\TaskMessage;
use App\Agent\TaskStatus;
use App\Models\AgentTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

#[Signature('agent:check-stale')]
#[Description('Помечает как прерванные задачи, которых нет у исполнителя (например, после перезапуска контейнера agent)')]
class AgentCheckStale extends Command
{
    public function handle(AgentRunner $runner, Nutgram $bot): int
    {
        $tasks = AgentTask::whereIn('status', [TaskStatus::Pending, TaskStatus::Running])
            ->where('updated_at', '<', now()->subMinutes(5))
            ->get();

        if ($tasks->isEmpty()) {
            return self::SUCCESS;
        }

        $health = $runner->health();
        $known = [$health['busy'], ...$health['queued']];

        foreach ($tasks as $task) {
            if (in_array($task->id, $known, true)) {
                continue;
            }

            $task->update([
                'status' => TaskStatus::Failed,
                'error' => __('agent.interrupted', locale: $task->user->preferredLocale()),
                'finished_at' => now(),
            ]);
            TaskMessage::update($bot, $task);
        }

        return self::SUCCESS;
    }
}
