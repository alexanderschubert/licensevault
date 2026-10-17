<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Notifications\PushMessage;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Web-Push (VAPID) an installierte PWAs, u. a. iPhone ab iOS 16.4 (nach „Zum Home-Bildschirm“). */
class WebPushChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'Web-Push';
    }

    public function configured(): bool
    {
        return filled(config('licensevault.push.public_key')) && filled(config('licensevault.push.private_key'));
    }

    public function send(PushMessage $message): int
    {
        if (! $this->configured()) {
            return 0;
        }
        $subs = PushSubscription::query()->get();
        if ($subs->isEmpty()) {
            return 0;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => (string) config('licensevault.push.subject'),
                'publicKey' => (string) config('licensevault.push.public_key'),
                'privateKey' => (string) config('licensevault.push.private_key'),
            ]], ['TTL' => 86400, 'urgency' => 'normal'], new Client(['timeout' => 15, 'connect_timeout' => 5, 'allow_redirects' => false]));

            $payload = json_encode(['title' => $message->title, 'body' => $message->body, 'url' => $message->path, 'tag' => $message->tag], JSON_UNESCAPED_UNICODE);
            foreach ($subs as $s) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $s->endpoint, 'publicKey' => $s->p256dh, 'authToken' => $s->auth, 'contentEncoding' => 'aes128gcm',
                ]), $payload);
            }

            $sent = 0;
            foreach ($webPush->flush() as $report) {
                $hash = PushSubscription::hashOf($report->getEndpoint());
                if ($report->isSuccess()) {
                    $sent++;
                    PushSubscription::where('endpoint_hash', $hash)->update(['last_used_at' => now()]);
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint_hash', $hash)->delete(); // abgemeldet/abgelaufen
                } else {
                    Log::warning('Web-Push fehlgeschlagen: '.mb_substr($report->getReason(), 0, 200));
                }
            }

            return $sent;
        } catch (\Throwable $e) {
            Log::warning('Web-Push-Fehler: '.$e::class.' '.mb_substr($e->getMessage(), 0, 200));

            return 0;
        }
    }
}
