<?php

namespace App\Models;

use App\Agent\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'parent_id',
    'prompt',
    'status',
    'session_id',
    'summary',
    'error',
    'commit',
    'revert_commit',
    'files',
    'migrations',
    'stat',
    'chat_id',
    'message_id',
    'started_at',
    'finished_at',
])]
class AgentTask extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'files' => 'array',
            'migrations' => 'array',
            'chat_id' => 'integer',
            'message_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function shortCommit(): ?string
    {
        return $this->commit ? substr($this->commit, 0, 7) : null;
    }
}
