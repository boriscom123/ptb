<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
