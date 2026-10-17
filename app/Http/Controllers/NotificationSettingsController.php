<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Notifications\Channels\GotifyChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Notifier;
use App\Notifications\PushMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function index(WebPushChannel $webPush, GotifyChannel $gotify)
    {
        return view('settings.notifications', [
            'webPushConfigured' => $webPush->configured(),
            'publicKey' => $webPush->configured() ? config('licensevault.push.public_key') : null,
            'gotifyConfigured' => $gotify->configured(),
            'gotifyHost' => $gotify->host(),
            'subscriptions' => PushSubscription::orderBy('created_at')->get(['id', 'label', 'created_at', 'last_used_at']),
            'days' => config('licensevault.reminder_days'),
        ]);
    }

    /** Registriert das aktuelle Gerät (Push-Abo aus dem Browser). */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);

        $sub = PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashOf($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'label' => PushSubscription::labelFromUserAgent($request->userAgent()),
            ],
        );

        return response()->json(['id' => $sub->id], 201);
    }

    public function unsubscribe(PushSubscription $subscription): RedirectResponse|JsonResponse
    {
        $subscription->delete();

        return request()->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Gerät abgemeldet.');
    }

    /** Endpoint → Abo-ID, damit ein Gerät sich selbst abmelden kann, ohne den Endpoint preiszugeben. */
    public function unsubscribeByEndpoint(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        PushSubscription::where('endpoint_hash', PushSubscription::hashOf($data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Notifier $notifier): RedirectResponse
    {
        if (! $notifier->anyConfigured()) {
            return back()->withErrors(['notify' => 'Es ist noch kein Kanal eingerichtet.']);
        }
        $result = $notifier->send(new PushMessage('LicenseVault', 'Testnachricht – Benachrichtigungen funktionieren.', '/erinnerungen', 'test'));
        $summary = collect($result)->map(fn ($n, $name) => "{$name}: {$n}")->implode(' · ');

        return collect($result)->sum() > 0
            ? back()->with('status', "Testnachricht gesendet ({$summary}).")
            : back()->withErrors(['notify' => "Testnachricht konnte nicht zugestellt werden ({$summary}). Prüfe Einrichtung und Server-Log."]);
    }
}
