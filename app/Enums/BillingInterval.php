<?php

namespace App\Enums;

enum BillingInterval: string
{
    case Once = 'once';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Einmalig',
            self::Monthly => 'Monatlich',
            self::Yearly => 'Jährlich',
        };
    }

    /** Kurzform für Preisangaben, z. B. "9,99 EUR / Monat". */
    public function suffix(): string
    {
        return match ($this) {
            self::Once => '',
            self::Monthly => ' / Monat',
            self::Yearly => ' / Jahr',
        };
    }

    /** Faktor zur Umrechnung auf Jahreskosten (einmalige Zahlungen zählen nicht). */
    public function perYear(): int
    {
        return match ($this) {
            self::Once => 0,
            self::Monthly => 12,
            self::Yearly => 1,
        };
    }
}
