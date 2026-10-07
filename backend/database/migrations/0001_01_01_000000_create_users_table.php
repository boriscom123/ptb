<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique();
            $table->string('username')->nullable()->index();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('language_code', 16)->nullable();
            $table->string('role', 32)->default('user')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('blocked_bot_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
