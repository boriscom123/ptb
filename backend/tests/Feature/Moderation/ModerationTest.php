<?php

namespace Tests\Feature\Moderation;

use App\Models\Chat;
use App\Models\ModerationEvent;
use App\Moderation\ChatAdmins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Hydrator\Hydrator;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Common\Update;
use SergiX44\Nutgram\Testing\FakeNutgram;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID = -1001234567890;

    private FakeNutgram $bot;

    private array $admins = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['nutgram.token' => '42:TEST']);
        $this->bot = app(Nutgram::class);

        $this->app->instance(ChatAdmins::class, new class($this->admins) extends ChatAdmins
        {
            public function __construct(private array &$admins) {}

            public function isAdmin(int $chatId, int $userId): bool
            {
                return in_array($userId, $this->admins, true);
            }

            public function forget(int $chatId): void {}
        });
    }

    private function chat(array $attributes = []): Chat
    {
        $chat = Chat::factory()->create(['telegram_id' => self::CHAT_ID, ...$attributes]);
        $chat->rules()->create(['rule' => 'links', 'enabled' => true, 'settings' => ['allowed_domains' => ['github.com']]]);
        $chat->rules()->create(['rule' => 'stop_words', 'enabled' => true, 'settings' => ['words' => ['казино'], 'action' => 'mute', 'mute_minutes' => 30]]);

        return $chat;
    }

    private function hear(array $update): FakeNutgram
    {
        $update = $this->bot->getContainer()->get(Hydrator::class)->hydrate(['update_id' => 1, ...$update], Update::class);

        return $this->bot->hearUpdate($update);
    }

    private function groupMessage(string $text, array $extra = [], int $fromId = 222, string $type = 'message'): array
    {
        return [$type => [
            'message_id' => 77,
            'date' => time(),
            'chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup', 'title' => 'Группа'],
            'from' => ['id' => $fromId, 'is_bot' => false, 'first_name' => 'Spam'],
            'text' => $text,
            ...$extra,
        ]];
    }

    private function link(string $text, string $url): array
    {
        // Telegram считает смещения в UTF-16 code units
        $utf16Length = fn (string $value) => strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) / 2;
        $offset = $utf16Length(substr($text, 0, strpos($text, $url)));

        return ['entities' => [['type' => 'url', 'offset' => $offset, 'length' => $utf16Length($url)]]];
    }

    public function test_message_with_forbidden_link_is_deleted_and_logged(): void
    {
        $chat = $this->chat();
        $text = 'Заходи 🔥 spam.example';
        $this->bot->willReceive(true);

        $this->hear($this->groupMessage($text, $this->link($text, 'spam.example')))->reply()
            ->assertReply('deleteMessage', ['chat_id' => self::CHAT_ID, 'message_id' => 77]);

        $event = ModerationEvent::sole();
        $this->assertSame($chat->id, $event->chat_id);
        $this->assertSame('links', $event->rule);
        $this->assertSame('delete', $event->action);
        $this->assertSame('spam.example', $event->reason);
        $this->assertSame(222, $event->user->telegram_id);
    }

    public function test_failed_deletion_is_logged_as_failed(): void
    {
        $this->chat();
        $this->bot->willReceive(['error_code' => 400, 'description' => 'Bad Request: message can\'t be deleted'], ok: false);

        $this->hear($this->groupMessage('казино'))->reply();

        $this->assertSame('failed', ModerationEvent::sole()->action);
    }

    public function test_allowed_link_and_clean_message_are_kept(): void
    {
        $this->chat();
        $text = 'см. github.com/repo';

        $this->hear($this->groupMessage($text, $this->link($text, 'github.com/repo')))->reply()->assertNoReply();
        $this->hear($this->groupMessage('просто текст'))->reply()->assertNoReply();

        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_stop_word_with_mute_action_restricts_author(): void
    {
        $this->chat();
        $this->bot->willReceive(true);

        $this->hear($this->groupMessage('Лучшее КАЗИНО'))->reply()
            ->assertReply('deleteMessage', index: 0)
            ->assertReply('restrictChatMember', ['chat_id' => self::CHAT_ID, 'user_id' => 222], index: 1);

        $this->assertSame('mute', ModerationEvent::sole()->action);
    }

    public function test_edited_message_is_moderated(): void
    {
        $this->chat();

        $this->hear($this->groupMessage('казино', type: 'edited_message'))->reply()->assertReply('deleteMessage');
    }

    public function test_chat_admins_are_exempt(): void
    {
        $this->chat();
        $this->admins[] = 222;

        $this->hear($this->groupMessage('казино'))->reply()->assertNoReply();
    }

    public function test_automatic_forward_from_linked_channel_is_exempt(): void
    {
        $this->chat();

        $this->hear($this->groupMessage('казино', [
            'is_automatic_forward' => true,
            'sender_chat' => ['id' => -1009999, 'type' => 'channel', 'title' => 'Канал'],
        ], fromId: 777000))->reply()->assertNoReply();
    }

    public function test_anonymous_admin_is_exempt(): void
    {
        $this->chat();

        $this->hear($this->groupMessage('казино', [
            'sender_chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup', 'title' => 'Группа'],
        ], fromId: 1087968824))->reply()->assertNoReply();
    }

    public function test_nothing_happens_when_moderation_disabled_or_rule_off(): void
    {
        $chat = $this->chat(['moderation_enabled' => false]);
        $this->hear($this->groupMessage('казино'))->reply()->assertNoReply();

        $chat->update(['moderation_enabled' => true]);
        $chat->rules()->update(['enabled' => false]);
        $this->hear($this->groupMessage('казино'))->reply()->assertNoReply();
    }

    public function test_nothing_happens_when_bot_is_not_admin(): void
    {
        $this->chat(['bot_status' => 'member']);

        $this->hear($this->groupMessage('казино'))->reply()->assertNoReply();
    }

    public function test_bot_added_as_admin_registers_chat_with_rights(): void
    {
        $this->hear(['my_chat_member' => [
            'chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup', 'title' => 'Новая группа', 'username' => 'newgroup'],
            'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Owner'],
            'date' => time(),
            'old_chat_member' => ['status' => 'left', 'user' => ['id' => 42, 'is_bot' => true, 'first_name' => 'Bot']],
            'new_chat_member' => [
                'status' => 'administrator',
                'user' => ['id' => 42, 'is_bot' => true, 'first_name' => 'Bot'],
                'can_be_edited' => false, 'is_anonymous' => false, 'can_manage_chat' => true,
                'can_delete_messages' => true, 'can_manage_video_chats' => false, 'can_restrict_members' => false,
                'can_promote_members' => false, 'can_change_info' => false, 'can_invite_users' => true,
                'can_post_stories' => false, 'can_edit_stories' => false, 'can_delete_stories' => false,
                'can_send_welcome_messages' => false,
            ],
        ]])->reply();

        $chat = Chat::sole();
        $this->assertSame('Новая группа', $chat->title);
        $this->assertSame('administrator', $chat->bot_status);
        $this->assertTrue($chat->can_delete_messages);
        $this->assertFalse($chat->can_restrict_members);
        $this->assertFalse($chat->moderation_enabled);
    }

    public function test_group_migration_keeps_chat_settings(): void
    {
        $chat = $this->chat(['telegram_id' => -555, 'type' => 'group']);

        $this->hear(['message' => [
            'message_id' => 1,
            'date' => time(),
            'chat' => ['id' => -555, 'type' => 'group', 'title' => 'Группа'],
            'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'Owner'],
            'migrate_to_chat_id' => self::CHAT_ID,
        ]])->reply();

        $chat->refresh();
        $this->assertSame(self::CHAT_ID, $chat->telegram_id);
        $this->assertSame('supergroup', $chat->type);
    }

    public function test_title_change_is_saved(): void
    {
        $chat = $this->chat();

        $this->hear($this->groupMessage('привет', ['chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup', 'title' => 'Новое имя']]))->reply();

        $this->assertSame('Новое имя', $chat->fresh()->title);
    }
}
