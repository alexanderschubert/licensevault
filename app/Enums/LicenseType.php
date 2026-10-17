<?php

namespace App\Enums;

enum LicenseType: string
{
    case Perpetual = 'perpetual';
    case Subscription = 'subscription';
    case Trial = 'trial';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Perpetual => 'Dauerlizenz',
            self::Subscription => 'Abonnement',
            self::Trial => 'Testversion',
            self::Other => 'Sonstiges',
        };
    }

    public function pill(): string
    {
        return match ($this) {
            self::Perpetual => 'pill-ok',
            self::Subscription => 'pill-ac',
            self::Trial => 'pill-wa',
            self::Other => '',
        };
    }
}
