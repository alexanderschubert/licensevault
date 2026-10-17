<?php

namespace App\Enums;

enum HomekitStatus: string
{
    case Active = 'active';
    case Offline = 'offline';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Eingerichtet',
            self::Offline => 'Offline',
            self::Retired => 'Ausgemustert',
        };
    }

    public function pill(): string
    {
        return match ($this) {
            self::Active => 'pill-ok',
            self::Offline => 'pill-wa',
            self::Retired => '',
        };
    }
}
