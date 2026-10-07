<?php

namespace App\Http\Resources;

use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Chat
 */
class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'telegram_id' => $this->telegram_id,
            'type' => $this->type,
            'title' => $this->title,
            'username' => $this->username,
            'bot_status' => $this->bot_status,
            'can_delete_messages' => $this->can_delete_messages,
            'can_restrict_members' => $this->can_restrict_members,
            'moderation_enabled' => $this->moderation_enabled,
            'events_count' => $this->whenCounted('events'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
