<?php

namespace App\Http\Controllers;

use App\Agent\TaskEvents;
use App\Models\AgentTask;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * События от исполнителя задач (контейнер agent).
 * Успешный ответ на «completed» подтверждает, что приложение работает с новым кодом.
 */
class AgentEventController
{
    public function __invoke(Request $request, TaskEvents $events): Response
    {
        abort_unless(
            is_string(config('agent.secret')) && hash_equals(config('agent.secret'), (string) $request->header('X-Agent-Secret')),
            401,
        );

        $data = $request->validate([
            'task_id' => ['required', 'integer'],
            'status' => ['required', 'in:running,completed,failed,cancelled'],
            'summary' => ['nullable', 'string'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'error' => ['nullable', 'string'],
            'commit' => ['nullable', 'string', 'size:40'],
            'files' => ['nullable', 'array'],
            'migrations' => ['nullable', 'array'],
            'stat' => ['nullable', 'string', 'max:255'],
        ]);

        $events->apply(AgentTask::findOrFail($data['task_id']), $data);

        return response()->noContent();
    }
}
