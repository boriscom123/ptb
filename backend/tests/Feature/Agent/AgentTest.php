<?php

namespace Tests\Feature\Agent;

use App\Agent\TaskStatus;
use App\Jobs\RevertAgentTask;
use App\Jobs\SubmitAgentTask;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use SergiX44\Nutgram\Hydrator\Hydrator;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Common\Update;
use SergiX44\Nutgram\Testing\FakeNutgram;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ID = 111;

    private const SECRET = 'test-agent-secret-0123456789abcdef0123456789';

    private FakeNutgram $bot;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bot.admin_telegram_id' => self::ADMIN_ID, 'agent.secret' => self::SECRET, 'agent.url' => 'http://agent.test']);
        $this->bot = app(Nutgram::class);
        $this->admin = User::factory()->admin()->create(['telegram_id' => self::ADMIN_ID, 'language_code' => 'ru']);
    }

    private function hear(array $update): FakeNutgram
    {
        $update = $this->bot->getContainer()->get(Hydrator::class)->hydrate(['update_id' => 1, ...$update], Update::class);

        return $this->bot->hearUpdate($update);
    }

    private function telegramUser(int $id): array
    {
        return ['id' => $id, 'is_bot' => false, 'first_name' => 'Admin', 'language_code' => 'ru'];
    }

    private function privateText(string $text, int $from = self::ADMIN_ID, array $extra = []): array
    {
        return ['message' => [
            'message_id' => 10,
            'date' => time(),
            'chat' => ['id' => $from, 'type' => 'private', 'first_name' => 'Admin'],
            'from' => $this->telegramUser($from),
            'text' => $text,
            ...$extra,
        ]];
    }

    private function callbackQuery(string $data, int $from = self::ADMIN_ID): array
    {
        return ['callback_query' => [
            'id' => 'cb1',
            'from' => $this->telegramUser($from),
            'chat_instance' => '1',
            'data' => $data,
            'message' => [
                'message_id' => 20,
                'date' => time(),
                'chat' => ['id' => $from, 'type' => 'private', 'first_name' => 'Admin'],
                'text' => 'confirm',
            ],
        ]];
    }

    private function task(array $attributes = []): AgentTask
    {
        return AgentTask::create([
            'user_id' => $this->admin->id,
            'prompt' => 'Добавь фильтр',
            'status' => TaskStatus::Completed,
            'chat_id' => self::ADMIN_ID,
            'message_id' => 20,
            ...$attributes,
        ]);
    }

    // Ключ черновика из кнопки «Запустить» в первом отправленном сообщении
    private function draftKey(): string
    {
        [$request] = array_values($this->bot->getRequestHistory()[0]);
        $markup = FakeNutgram::getActualData($request)['reply_markup'];
        $markup = is_string($markup) ? json_decode($markup, true) : $markup;

        return explode(':', $markup['inline_keyboard'][0][0]['callback_data'])[2];
    }

    public function test_admin_text_creates_draft_with_confirmation(): void
    {
        $this->hear($this->privateText('Добавь фильтр по роли'))->reply()
            ->assertReply('sendMessage', ['chat_id' => self::ADMIN_ID]);

        $this->assertSame(16, strlen($this->draftKey()));
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    public function test_regular_user_text_gets_hint(): void
    {
        User::factory()->create(['telegram_id' => 222]);

        $this->hear($this->privateText('Добавь фильтр', 222))->reply()
            ->assertReplyText('Не понимаю эту команду. Список команд — /help');
    }

    public function test_run_creates_task_and_submits_it(): void
    {
        Queue::fake();
        $this->hear($this->privateText('Добавь фильтр по роли'))->reply();
        $key = $this->draftKey();

        $this->hear($this->callbackQuery("agent:run:$key"))->reply()->assertCalled('editMessageText');

        $task = AgentTask::sole();
        $this->assertSame('Добавь фильтр по роли', $task->prompt);
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertSame(20, $task->message_id);
        Queue::assertPushed(SubmitAgentTask::class, fn ($job) => $job->task->is($task));
    }

    public function test_draft_can_be_used_only_once(): void
    {
        Queue::fake();
        $this->hear($this->privateText('Задача'))->reply();
        $key = $this->draftKey();

        $this->hear($this->callbackQuery("agent:run:$key"))->reply();
        $this->hear($this->callbackQuery("agent:run:$key"))->reply();

        $this->assertDatabaseCount('agent_tasks', 1);
    }

    public function test_non_admin_cannot_press_agent_buttons(): void
    {
        Queue::fake();
        User::factory()->create(['telegram_id' => 222]);
        $task = $this->task(['commit' => str_repeat('a', 40)]);

        $this->hear($this->callbackQuery("agent:revert-yes:{$task->id}", 222))->reply()
            ->assertReply('answerCallbackQuery', ['show_alert' => true]);

        Queue::assertNothingPushed();
        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
    }

    public function test_reply_to_result_creates_followup_with_parent_session(): void
    {
        Queue::fake();
        $parent = $this->task(['session_id' => 'session-1', 'message_id' => 5]);

        $this->hear($this->privateText('Ещё добавь сортировку', extra: ['reply_to_message' => [
            'message_id' => 5, 'date' => time(), 'chat' => ['id' => self::ADMIN_ID, 'type' => 'private'], 'text' => 'result',
        ]]))->reply();
        $this->hear($this->callbackQuery('agent:run:'.$this->draftKey()))->reply();

        $task = AgentTask::where('parent_id', $parent->id)->sole();

        Http::fake(['agent.test/*' => Http::response(['position' => 1], 202)]);
        // Queue::fake перехватывает и dispatchSync — вызываем задачу напрямую
        app()->call([new SubmitAgentTask($task), 'handle']);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://agent.test/tasks'
            && $request['id'] === $task->id
            && $request['session_id'] === 'session-1'
            && $request->header('X-Agent-Secret')[0] === self::SECRET);
    }

    public function test_unavailable_runner_fails_task(): void
    {
        Http::fake(['agent.test/*' => Http::response(null, 500)]);
        $task = $this->task(['status' => TaskStatus::Pending]);

        SubmitAgentTask::dispatchSync($task);

        $this->assertSame(TaskStatus::Failed, $task->fresh()->status);
        $this->assertStringContainsString('Агент недоступен', $task->fresh()->error);
    }

    public function test_events_require_secret(): void
    {
        $task = $this->task(['status' => TaskStatus::Pending]);

        $this->postJson('/api/agent/events', ['task_id' => $task->id, 'status' => 'running'])->assertUnauthorized();
        $this->postJson('/api/agent/events', ['task_id' => $task->id, 'status' => 'running'], ['X-Agent-Secret' => 'wrong'])->assertUnauthorized();

        $this->assertSame(TaskStatus::Pending, $task->fresh()->status);
    }

    public function test_completed_event_saves_result_and_runs_migrations(): void
    {
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true]);
        $task = $this->task(['status' => TaskStatus::Running]);

        $this->postJson('/api/agent/events', [
            'task_id' => $task->id,
            'status' => 'completed',
            'summary' => 'Добавил фильтр',
            'session_id' => 'session-2',
            'commit' => str_repeat('b', 40),
            'files' => ['M backend/app/X.php'],
            'migrations' => ['backend/database/migrations/2026_x.php'],
            'stat' => '1 file changed',
        ], ['X-Agent-Secret' => self::SECRET])->assertNoContent();

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertSame('Добавил фильтр', $task->summary);
        $this->assertSame('session-2', $task->session_id);
        $this->assertSame('bbbbbbb', $task->shortCommit());
        $this->assertNotNull($task->finished_at);
    }

    public function test_completed_event_without_migrations_does_not_migrate(): void
    {
        Artisan::shouldReceive('call')->never();
        $task = $this->task(['status' => TaskStatus::Running]);

        $this->postJson('/api/agent/events', ['task_id' => $task->id, 'status' => 'completed', 'summary' => 'Ответ на вопрос'], ['X-Agent-Secret' => self::SECRET])
            ->assertNoContent();

        $this->assertNull($task->fresh()->commit);
    }

    public function test_revert_asks_for_confirmation_then_queues_job(): void
    {
        Queue::fake();
        $task = $this->task(['commit' => str_repeat('c', 40)]);

        $this->hear($this->callbackQuery("agent:revert:{$task->id}"))->reply()->assertCalled('editMessageText');
        Queue::assertNothingPushed();

        $this->hear($this->callbackQuery("agent:revert-yes:{$task->id}"))->reply();
        $this->assertSame(TaskStatus::Reverting, $task->fresh()->status);
        Queue::assertPushed(RevertAgentTask::class);
    }

    public function test_revert_job_resets_task_migrations_and_reverts_commit(): void
    {
        Http::fake(['agent.test/revert' => Http::response(['commit' => str_repeat('d', 40)])]);
        Artisan::shouldReceive('call')->once()->with('migrate:reset', [
            '--path' => ['database/migrations/2026_x.php'],
            '--force' => true,
        ]);
        $task = $this->task([
            'status' => TaskStatus::Reverting,
            'commit' => str_repeat('c', 40),
            'migrations' => ['backend/database/migrations/2026_x.php'],
        ]);

        RevertAgentTask::dispatchSync($task);

        $task->refresh();
        $this->assertSame(TaskStatus::Reverted, $task->status);
        $this->assertSame(str_repeat('d', 40), $task->revert_commit);
        Http::assertSent(fn (Request $request) => $request['commit'] === str_repeat('c', 40));
    }

    public function test_failed_revert_restores_migrations(): void
    {
        Http::fake(['agent.test/revert' => Http::response(['error' => 'conflict'], 409)]);
        Artisan::shouldReceive('call')->once()->with('migrate:reset', \Mockery::any());
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true]);
        $task = $this->task(['status' => TaskStatus::Reverting, 'commit' => str_repeat('c', 40), 'migrations' => ['backend/database/migrations/2026_x.php']]);

        RevertAgentTask::dispatchSync($task);

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertStringContainsString('conflict', $task->error);
    }

    public function test_stale_tasks_unknown_to_runner_are_marked_failed(): void
    {
        Http::fake(['agent.test/health' => Http::response(['busy' => null, 'queued' => []])]);
        $stale = $this->task(['status' => TaskStatus::Running]);
        AgentTask::whereKey($stale->id)->update(['updated_at' => now()->subMinutes(10)]);
        $fresh = $this->task(['status' => TaskStatus::Running, 'message_id' => 21]);

        $this->artisan('agent:check-stale')->assertSuccessful();

        $this->assertSame(TaskStatus::Failed, $stale->fresh()->status);
        $this->assertSame(TaskStatus::Running, $fresh->fresh()->status);
    }
}
