<?php

namespace App\Enums;

enum DeviceProtocol: string
{
    case HomeKit = 'homekit';
    case Matter = 'matter';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::HomeKit => 'HomeKit',
            self::Matter => 'Matter',
            self::Both => 'HomeKit + Matter',
        };
    }

    /** Hinweis, was der Eintrag bedeutet (für Auswahlfelder). */
    public function hint(): string
    {
        return match ($this) {
            self::HomeKit => 'Reines HomeKit-Gerät (Code X-HM://)',
            self::Matter => 'Matter-Gerät (Code MT:)',
            self::Both => 'Matter-Gerät, das auch in Apple Home / HomeKit läuft',
        };
    }

    /** @return list<self> Einzelne Standards, die dieser Eintrag abdeckt. */
    public function parts(): array
    {
        return match ($this) {
            self::Both => [self::HomeKit, self::Matter],
            default => [$this],
        };
    }

    public function pill(): string
    {
        return match ($this) {
            self::HomeKit => 'pill-wa',
            self::Matter, self::Both => 'pill-ac',
        };
    }

    public function codeLabel(): string
    {
        return match ($this) {
            self::HomeKit => 'Einrichtungscode',
            self::Matter => 'Manueller Kopplungscode',
            self::Both => 'Einrichtungs-/Kopplungscode',
        };
    }
}
