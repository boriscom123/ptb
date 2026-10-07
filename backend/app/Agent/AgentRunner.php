<?php

namespace App\Agent;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP-клиент исполнителя задач в контейнере agent.
 */
class AgentRunner
{
    public function submit(int $taskId, string $prompt, ?string $sessionId): Response
    {
        return $this->request()->post('/tasks', [
            'id' => $taskId,
            'prompt' => $prompt,
            'session_id' => $sessionId,
        ]);
    }

    public function cancel(int $taskId): Response
    {
        return $this->request()->post('/cancel', ['id' => $taskId]);
    }

    public function revert(string $commit): Response
    {
        // Откат ждёт в общей очереди операций с репозиторием
        return $this->request()->timeout(600)->post('/revert', ['commit' => $commit]);
    }

    /**
     * @return array{busy: ?int, queued: list<int>}
     */
    public function health(): array
    {
        return $this->request()->get('/health')->throw()->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('agent.url'))
            ->withHeaders(['X-Agent-Secret' => (string) config('agent.secret')])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }
}
