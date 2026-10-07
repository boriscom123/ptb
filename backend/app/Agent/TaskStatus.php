<?php

namespace App\Agent;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Reverting = 'reverting';
    case Reverted = 'reverted';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Running], true);
    }
}
