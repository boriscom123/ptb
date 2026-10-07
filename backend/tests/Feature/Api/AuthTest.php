<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:TEST-TOKEN';

    protected function setUp(): void
    {
        parent::setUp();

        config(['nutgram.token' => self::BOT_TOKEN, 'bot.admin_telegram_id' => 111]);
    }

    public static function initData(array $user, ?int $authDate = null, string $botToken = self::BOT_TOKEN): string
    {
        $fields = [
            'auth_date' => (string) ($authDate ?? time()),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
            'user' => json_encode($user, JSON_UNESCAPED_UNICODE),
        ];
        ksort($fields);

        $dataCheckString = implode("\n", array_map(fn ($k, $v) => "$k=$v", array_keys($fields), $fields));
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query($fields);
    }

    private function telegramUser(int $id = 222): array
    {
        return ['id' => $id, 'first_name' => 'Иван', 'last_name' => 'Петров', 'username' => 'ivan', 'language_code' => 'ru'];
    }

    public function test_valid_init_data_returns_token_and_creates_user(): void
    {
        $response = $this->postJson('/api/auth/telegram', ['init_data' => self::initData($this->telegramUser())]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.telegram_id', 222)
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.first_name', 'Иван');

        $this->assertDatabaseHas('users', ['telegram_id' => 222, 'username' => 'ivan']);

        $this->withToken($response->json('token'))->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.telegram_id', 222);
    }

    public function test_configured_admin_gets_admin_role_on_login(): void
    {
        $this->postJson('/api/auth/telegram', ['init_data' => self::initData($this->telegramUser(111))])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.is_protected', true);
    }

    public function test_tampered_init_data_is_rejected(): void
    {
        $initData = str_replace('ivan', 'admin', self::initData($this->telegramUser()));

        $this->postJson('/api/auth/telegram', ['init_data' => $initData])->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_init_data_signed_with_other_token_is_rejected(): void
    {
        $initData = self::initData($this->telegramUser(), botToken: '999:OTHER');

        $this->postJson('/api/auth/telegram', ['init_data' => $initData])->assertUnauthorized();
    }

    public function test_expired_init_data_is_rejected(): void
    {
        $initData = self::initData($this->telegramUser(), authDate: time() - 86401);

        $this->postJson('/api/auth/telegram', ['init_data' => $initData])->assertUnauthorized();
    }

    public function test_error_messages_follow_accept_language(): void
    {
        $this->postJson('/api/auth/telegram', ['init_data' => 'hash=bad&user=x'], ['Accept-Language' => 'en'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Could not verify Telegram data. Please reopen the app.');
    }

    public function test_me_requires_valid_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->withToken('not-a-jwt')->getJson('/api/me')->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->travel(-2)->hours();
        $token = app(JwtService::class)->issue($user)['token'];
        $this->travelBack();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_token_of_deleted_user_is_rejected(): void
    {
        $user = User::factory()->create(['role' => Role::Admin]);
        $token = app(JwtService::class)->issue($user)['token'];
        $user->delete();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }
}
