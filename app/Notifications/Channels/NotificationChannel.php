<?php

namespace App\Notifications\Channels;

use App\Notifications\PushMessage;

interface NotificationChannel
{
    public function name(): string;

    /** Ist der Kanal eingerichtet (Schlüssel/URL gesetzt)? */
    public function configured(): bool;

    /** @return int Anzahl erfolgreich zugestellter Nachrichten/Geräte */
    public function send(PushMessage $message): int;
}
