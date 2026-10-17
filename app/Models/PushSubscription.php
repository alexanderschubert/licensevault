<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'label', 'last_used_at'];

    /** Endpoint und Schlüssel sind Zugangsdaten zum Push-Dienst: nie serialisieren. */
    protected $hidden = ['endpoint', 'p256dh', 'auth'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public static function hashOf(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /** Grobe Gerätebezeichnung aus dem User-Agent, z. B. "iPhone (Safari)". */
    public static function labelFromUserAgent(?string $ua): string
    {
        $ua = (string) $ua;
        $device = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Gerät',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Browser',
        };

        return "{$device} ({$browser})";
    }
}
