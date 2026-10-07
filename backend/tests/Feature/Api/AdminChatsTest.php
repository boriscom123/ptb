<?php

namespace Tests\Feature\Api;

use App\Models\Chat;
use App\Models\ModerationEvent;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminChatsTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = app(JwtService::class)->issue(User::factory()->admin()->create())['token'];
    }

    public function test_regular_user_cannot_access_chats(): void
    {
        $token = app(JwtService::class)->issue(User::factory()->create())['token'];

        $this->withToken($token)->getJson('/api/admin/chats')->assertForbidden();
    }

    public function test_lists_chats_with_event_counts(): void
    {
        $chat = Chat::factory()->create(['title' => 'Alpha']);
        ModerationEvent::create(['chat_id' => $chat->id, 'rule' => 'links', 'action' => 'delete']);
        Chat::factory()->create(['title' => 'Beta', 'bot_status' => 'left']);

        $this->withToken($this->token)->getJson('/api/admin/chats')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Alpha')
            ->assertJsonPath('data.0.events_count', 1)
            ->assertJsonPath('data.1.title', 'Beta');
    }

    public function test_show_returns_all_rules_with_defaults(): void
    {
        $chat = Chat::factory()->create();

        $this->withToken($this->token)->getJson("/api/admin/chats/{$chat->id}")
            ->assertOk()
            ->assertJsonPath('rules.0.key', 'links')
            ->assertJsonPath('rules.0.enabled', false)
            ->assertJsonPath('rules.0.settings', ['allowed_domains' => [], 'action' => 'delete', 'mute_minutes' => 60])
            ->assertJsonPath('rules.1.key', 'stop_words');
    }

    public function test_toggles_moderation(): void
    {
        $chat = Chat::factory()->create(['moderation_enabled' => false]);

        $this->withToken($this->token)->patchJson("/api/admin/chats/{$chat->id}", ['moderation_enabled' => true])
            ->assertOk()->assertJsonPath('data.moderation_enabled', true);

        $this->assertTrue($chat->fresh()->moderation_enabled);
    }

    public function test_saves_rule_settings(): void
    {
        $chat = Chat::factory()->create();

        $this->withToken($this->token)->putJson("/api/admin/chats/{$chat->id}/rules/stop_words", [
            'enabled' => true,
            'settings' => ['words' => ['казино', 'спам*'], 'action' => 'mute', 'mute_minutes' => 15, 'unknown' => 'x'],
        ])->assertOk()->assertJsonPath('rules.1.enabled', true);

        $rule = $chat->rules()->sole();
        $this->assertSame('stop_words', $rule->rule);
        $this->assertSame(['words' => ['казино', 'спам*'], 'action' => 'mute', 'mute_minutes' => 15], $rule->settings);
    }

    public function test_rejects_invalid_rule_settings(): void
    {
        $chat = Chat::factory()->create();

        $this->withToken($this->token)->putJson("/api/admin/chats/{$chat->id}/rules/stop_words", [
            'enabled' => true,
            'settings' => ['words' => [''], 'action' => 'ban', 'mute_minutes' => 0],
        ])->assertUnprocessable()->assertJsonValidationErrors(['settings.words.0', 'settings.action', 'settings.mute_minutes']);

        $this->withToken($this->token)->putJson("/api/admin/chats/{$chat->id}/rules/unknown", ['enabled' => true])->assertNotFound();
    }

    public function test_lists_events_newest_first(): void
    {
        $chat = Chat::factory()->create();
        $user = User::factory()->create(['username' => 'spammer']);
        ModerationEvent::create(['chat_id' => $chat->id, 'rule' => 'links', 'action' => 'delete', 'reason' => 'old.example']);
        ModerationEvent::create(['chat_id' => $chat->id, 'user_id' => $user->id, 'rule' => 'stop_words', 'action' => 'mute', 'reason' => 'казино']);

        $this->withToken($this->token)->getJson("/api/admin/chats/{$chat->id}/events")
            ->assertOk()
            ->assertJsonPath('data.0.reason', 'казино')
            ->assertJsonPath('data.0.user.username', 'spammer')
            ->assertJsonPath('data.1.user', null);
    }
}
