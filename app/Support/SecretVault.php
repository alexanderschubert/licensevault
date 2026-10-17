<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * Versionierte Verschlüsselung für Geheimnisse (Lizenzschlüssel, später HomeKit-Codes).
 * Format: "v1:" + Laravel-Encryption (APP_KEY). Eine spätere Schlüsselstrategie
 * kann über ein neues Versionspräfix eingeführt werden, ohne alte Daten zu brechen.
 */
class SecretVault
{
    private const V1 = 'v1:';

    public static function encrypt(string $plain): string
    {
        return self::V1.Crypt::encryptString($plain);
    }

    public static function decrypt(string $stored): string
    {
        if (str_starts_with($stored, self::V1)) {
            return Crypt::decryptString(substr($stored, strlen(self::V1)));
        }

        throw new \RuntimeException('Unbekanntes Verschlüsselungsformat.');
    }
}
