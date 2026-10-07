<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Задача-родитель: доработка продолжает её сессию Claude Code
            $table->foreignId('parent_id')->nullable()->constrained('agent_tasks')->nullOnDelete();
            $table->text('prompt');
            $table->string('status', 16)->index();
            $table->string('session_id')->nullable();
            $table->text('summary')->nullable();
            $table->text('error')->nullable();
            $table->string('commit', 40)->nullable();
            $table->string('revert_commit', 40)->nullable();
            $table->jsonb('files')->nullable();
            $table->jsonb('migrations')->nullable();
            $table->string('stat')->nullable();
            // Сообщение со статусом задачи в Telegram
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_tasks');
    }
};
