<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Jobs\NotifyRoleChanged;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bot.admin_telegram_id' => 111]);
        $this->admin = User::factory()->admin()->create(['telegram_id' => 500]);
    }

    private function actingWithToken(User $user): static
    {
        return $this->withToken(app(JwtService::class)->issue($user)['token']);
    }

    public function test_regular_user_cannot_access_admin_api(): void
    {
        $user = User::factory()->create();

        $this->actingWithToken($user)->getJson('/api/admin/users')->assertForbidden();
        $this->actingWithToken($user)->patchJson("/api/admin/users/{$this->admin->id}", ['role' => 'user'])->assertForbidden();
    }

    public function test_admin_lists_users_with_pagination(): void
    {
        User::factory()->count(25)->create();

        $this->actingWithToken($this->admin)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonStructure(['data' => [['id', 'telegram_id', 'username', 'first_name', 'role', 'last_seen_at']]]);
    }

    public function test_admin_searches_by_name_username_and_telegram_id(): void
    {
        User::factory()->create(['first_name' => 'Мария', 'username' => 'masha', 'telegram_id' => 777000]);
        User::factory()->create(['first_name' => 'Пётр', 'username' => 'petya']);

        $this->actingWithToken($this->admin)->getJson('/api/admin/users?search=мар')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.username', 'masha');
        $this->actingWithToken($this->admin)->getJson('/api/admin/users?search=@PETYA')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.username', 'petya');
        $this->actingWithToken($this->admin)->getJson('/api/admin/users?search=777000')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.username', 'masha');
        $this->actingWithToken($this->admin)->getJson('/api/admin/users?search=100%25')
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_filters_by_role(): void
    {
        User::factory()->count(3)->create();

        $this->actingWithToken($this->admin)->getJson('/api/admin/users?role=admin')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->admin->id);
        $this->actingWithToken($this->admin)->getJson('/api/admin/users?role=owner')->assertUnprocessable();
    }

    public function test_admin_views_single_user(): void
    {
        $user = User::factory()->create(['username' => 'masha']);

        $this->actingWithToken($this->admin)->getJson("/api/admin/users/{$user->id}")
            ->assertOk()->assertJsonPath('data.username', 'masha');
        $this->actingWithToken($this->admin)->getJson('/api/admin/users/999999')->assertNotFound();
    }

    public function test_admin_changes_user_role_and_user_is_notified(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingWithToken($this->admin)->patchJson("/api/admin/users/{$user->id}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->assertSame(Role::Admin, $user->fresh()->role);
        Queue::assertPushed(NotifyRoleChanged::class, fn ($job) => $job->user->is($user));
    }

    public function test_same_role_does_not_notify(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingWithToken($this->admin)->patchJson("/api/admin/users/{$user->id}", ['role' => 'user'])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $this->actingWithToken($this->admin)->patchJson("/api/admin/users/{$this->admin->id}", ['role' => 'user'])
            ->assertUnprocessable();

        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_protected_admin_role_cannot_be_changed(): void
    {
        $owner = User::factory()->admin()->create(['telegram_id' => 111]);

        $this->actingWithToken($this->admin)->patchJson("/api/admin/users/{$owner->id}", ['role' => 'user'])
            ->assertUnprocessable();

        $this->assertSame(Role::Admin, $owner->fresh()->role);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingWithToken($this->admin)->patchJson("/api/admin/users/{$user->id}", ['role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }
}
