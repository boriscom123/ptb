<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';

    public function label(?string $locale = null): string
    {
        return __('roles.'.$this->value, locale: $locale);
    }
}
