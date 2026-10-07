<?php

namespace App\Moderation;

enum Action: string
{
    // Удалить сообщение
    case Delete = 'delete';
    // Удалить сообщение и запретить автору писать на mute_minutes минут
    case Mute = 'mute';
}
