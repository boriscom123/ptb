<?php

namespace Tests\Feature\Telegram;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User as TelegramUser;
use SergiX44\Nutgram\Testing\FakeNutgram;
use Tests\TestCase;

class BotTest extends TestCase
{
    use RefreshDatabase;

    private FakeNutgram $bot;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bot.admin_telegram_id' => 111]);
        $this->bot = app(Nutgram::class);
    }

    private function telegram(int $id, string $language = 'ru', ChatType $chatType = ChatType::PRIVATE): FakeNutgram
    {
        $user = TelegramUser::make(id: $id, is_bot: false, first_name: 'Иван', last_name: 'Петров', username: 'ivan', language_code: $language);
        $chat = Chat::make(id: $chatType === ChatType::PRIVATE ? $id : -100500, type: $chatType);

        return $this->bot->setCommonUser($user)->setCommonChat($chat);
    }

    public function test_start_registers_user_and_greets_in_russian(): void
    {
        $this->telegram(222)->hearText('/start')->reply()
            ->assertReplyText("Привет, <b>Иван</b>! 👋\n\nВаша роль: <b>пользователь</b>.\n\nСписок команд — /help");

        $user = User::where('telegram_id', 222)->sole();
        $this->assertSame(Role::User, $user->role);
        $this->assertSame('ivan', $user->username);
        $this->assertNotNull($user->started_at);
        $this->assertNotNull($user->last_seen_at);
    }

    public function test_configured_admin_gets_admin_role(): void
    {
        User::factory()->create(['telegram_id' => 111, 'role' => Role::User]);

        $this->telegram(111)->hearText('/start')->reply()->assertCalled('sendMessage');

        $this->assertSame(Role::Admin, User::where('telegram_id', 111)->sole()->role);
    }

    public function test_unsupported_language_falls_back_to_english(): void
    {
        $this->telegram(222, 'de')->hearText('/help')->reply()
            ->assertReplyText("<b>Commands</b>\n\n/start — get started\n/help — command list");
    }

    public function test_commands_are_ignored_in_groups(): void
    {
        $this->telegram(222, chatType: ChatType::SUPERGROUP)->hearText('/start')->reply()->assertNoReply();
    }

    public function test_unknown_private_message_gets_hint(): void
    {
        $this->telegram(222)->hearText('привет')->reply()->assertReplyText('Не понимаю эту команду. Список команд — /help');
    }

    public function test_group_messages_get_no_hint(): void
    {
        $this->telegram(222, chatType: ChatType::SUPERGROUP)->hearText('привет')->reply()->assertNoReply();
    }
}
