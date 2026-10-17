<?php

namespace App\Notifications;

use App\Models\Reminder;
use App\Notifications\Channels\NotificationChannel;
use Illuminate\Support\Collection;

/** Verteilt Benachrichtigungen auf alle eingerichteten Kanäle (Web-Push, Gotify). */
class Notifier
{
    /** @param list<NotificationChannel> $channels */
    public function __construct(private array $channels) {}

    /** @return list<NotificationChannel> */
    public function channels(): array
    {
        return $this->channels;
    }

    public function anyConfigured(): bool
    {
        return collect($this->channels)->contains(fn (NotificationChannel $c) => $c->configured());
    }

    /** @return array<string,int> Kanalname => zugestellte Nachrichten */
    public function send(PushMessage $message): array
    {
        $result = [];
        foreach ($this->channels as $channel) {
            if ($channel->configured()) {
                $result[$channel->name()] = $channel->send($message);
            }
        }

        return $result;
    }

    /**
     * Meldet neu entstandene Erinnerungen (bis zu 3 einzeln, sonst als Sammelnachricht).
     *
     * @param  Collection<int, Reminder>  $reminders
     * @return int Anzahl versendeter Nachrichten
     */
    public function notifyReminders(Collection $reminders): int
    {
        if ($reminders->isEmpty() || ! $this->anyConfigured()) {
            return 0;
        }
        $reminders->each(fn (Reminder $r) => $r->loadMissing('license'));

        if ($reminders->count() > 3) {
            $this->send(new PushMessage(
                'LicenseVault: '.$reminders->count().' neue Erinnerungen',
                $reminders->take(5)->map(fn (Reminder $r) => $r->license->name.' – '.$r->message())->implode("\n").($reminders->count() > 5 ? "\n…" : ''),
                '/erinnerungen', 'reminders-summary',
            ));

            return 1;
        }

        foreach ($reminders as $r) {
            $this->send(new PushMessage(
                $r->license->name,
                $r->message().' ('.$r->due_on->format('d.m.Y').')',
                '/lizenzen/'.$r->license_id, 'reminder-'.$r->id,
            ));
        }

        return $reminders->count();
    }
}
