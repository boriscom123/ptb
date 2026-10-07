<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_id', 'rule', 'enabled', 'settings'])]
class ChatRule extends Model
{
    protected $attributes = [
        'enabled' => false,
        'settings' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}
