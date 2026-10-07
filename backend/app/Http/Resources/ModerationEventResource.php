<?php

namespace App\Http\Resources;

use App\Models\ModerationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModerationEvent
 */
class ModerationEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule' => $this->rule,
            'action' => $this->action,
            'reason' => $this->reason,
            'message_text' => $this->message_text,
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
