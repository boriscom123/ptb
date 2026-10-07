<?php

namespace App\Jobs;

use App\Agent\AgentRunner;
use App\Agent\TaskMessage;
use App\Agent\TaskStatus;
use App\Models\AgentTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use SergiX44\Nutgram\Nutgram;
use Throwable;

class SubmitAgentTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public AgentTask $task) {}

    public function handle(AgentRunner $runner, Nutgram $bot): void
    {
        try {
            $response = $runner->submit($this->task->id, $this->task->prompt, $this->task->parent?->session_id);
            $error = $response->successful() ? null : $response->json('error', "HTTP {$response->status()}");
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        if ($error !== null) {
            $this->task->update([
                'status' => TaskStatus::Failed,
                'error' => __('agent.runner_unavailable', ['error' => $error], $this->task->user->preferredLocale()),
                'finished_at' => now(),
            ]);
            TaskMessage::update($bot, $this->task);
        }
    }
}
