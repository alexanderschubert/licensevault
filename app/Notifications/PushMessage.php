<?php

namespace App\Notifications;

/** Inhalt einer Benachrichtigung – bewusst ohne Geheimnisse (nur Namen, Fristen, Pfad). */
final class PushMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $path = '/erinnerungen',
        public readonly string $tag = 'licensevault',
    ) {}

    /** Absolute Adresse für Klick-Aktionen (nur wenn APP_URL gesetzt ist). */
    public function url(): ?string
    {
        $base = rtrim((string) config('app.url'), '/');

        return str_starts_with($base, 'http') ? $base.$this->path : null;
    }
}
