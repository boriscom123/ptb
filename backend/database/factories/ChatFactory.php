<?php

namespace Database\Factories;

use App\Models\Chat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chat>
 */
class ChatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'telegram_id' => -fake()->unique()->numberBetween(1_000_000_000_000, 1_009_999_999_999),
            'type' => 'supergroup',
            'title' => fake()->words(2, true),
            'bot_status' => 'administrator',
            'can_delete_messages' => true,
            'can_restrict_members' => true,
            'moderation_enabled' => true,
        ];
    }
}
