<?php

namespace App\Notifications\Channels;

use App\Notifications\PushMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Gotify (selbst gehosteter Push-Server): POST {GOTIFY_URL}/message mit App-Token im Header. */
class GotifyChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'Gotify';
    }

    public function configured(): bool
    {
        return filled(config('licensevault.gotify.url')) && filled(config('licensevault.gotify.token'));
    }

    public function host(): ?string
    {
        return parse_url((string) config('licensevault.gotify.url'), PHP_URL_HOST) ?: null;
    }

    public function send(PushMessage $message): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $payload = [
            'title' => $message->title,
            'message' => $message->body,
            'priority' => (int) config('licensevault.gotify.priority', 5),
        ];
        if ($url = $message->url()) {
            $payload['extras'] = ['client::notification' => ['click' => ['url' => $url]]];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Gotify-Key' => (string) config('licensevault.gotify.token')]) // Token nie in der URL/in Logs
                ->acceptJson()
                ->post(rtrim((string) config('licensevault.gotify.url'), '/').'/message', $payload);
        } catch (\Throwable $e) {
            Log::warning('Gotify nicht erreichbar: '.$e::class);

            return 0;
        }

        if (! $response->successful()) {
            Log::warning('Gotify lehnte die Nachricht ab (HTTP '.$response->status().').');

            return 0;
        }

        return 1;
    }
}
