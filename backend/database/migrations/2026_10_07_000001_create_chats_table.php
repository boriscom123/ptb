<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique();
            $table->string('type', 16);
            $table->string('title');
            $table->string('username')->nullable();
            // Статус бота в чате: administrator, member, left, kicked
            $table->string('bot_status', 16);
            $table->boolean('can_delete_messages')->default(false);
            $table->boolean('can_restrict_members')->default(false);
            $table->boolean('moderation_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('chat_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->string('rule', 32);
            $table->boolean('enabled')->default(false);
            $table->jsonb('settings')->default('{}');
            $table->timestamps();

            $table->unique(['chat_id', 'rule']);
        });

        Schema::create('moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule', 32);
            $table->string('action', 16);
            $table->string('reason')->nullable();
            $table->bigInteger('message_id')->nullable();
            $table->text('message_text')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['chat_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_events');
        Schema::dropIfExists('chat_rules');
        Schema::dropIfExists('chats');
    }
};
