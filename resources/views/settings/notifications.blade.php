@extends('layouts.app')
@section('title', 'Benachrichtigungen')
@section('content')
<h1 class="text-2xl font-bold tracking-tight">Einstellungen</h1>
<div class="mt-4"><x-settings-tabs /></div>
<x-flash />
@error('notify')<div class="pill pill-er mb-4 !px-3.5 !py-2 text-sm" role="alert">{{ $message }}</div>@enderror

<div class="grid max-w-5xl gap-5 lg:grid-cols-2">
    {{-- Web-Push --}}
    <section class="card p-6" @if($webPushConfigured) x-data="pushSettings(@js(['publicKey' => $publicKey, 'subscribeUrl' => route('push.subscribe'), 'unsubscribeUrl' => route('push.unsubscribe-endpoint')]))" @endif>
        <div class="mb-1 flex items-center justify-between"><h2 class="font-semibold">Web-Push (iPhone, Android, Desktop)</h2>
            <span class="pill {{ $webPushConfigured ? 'pill-ok' : '' }}">{{ $webPushConfigured ? 'eingerichtet' : 'nicht eingerichtet' }}</span></div>
        <p class="text-sm text-ink2">Nachrichten direkt aufs Gerät, ohne zusätzliche App. Auf dem iPhone ab iOS 16.4 und nur in der <b>zum Home-Bildschirm hinzugefügten</b> App.</p>

        @if(! $webPushConfigured)
            <div class="mt-4 rounded-xl bg-panel2 p-4 text-sm text-ink2">
                <p class="font-medium text-ink">Einrichtung (einmalig)</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>Schlüssel erzeugen: <code class="rounded bg-panel px-1.5 py-0.5">docker compose exec -u app app php artisan licensevault:vapid-keys</code></li>
                    <li>Die drei <code>VAPID_*</code>-Zeilen in die <code>.env</code> eintragen.</li>
                    <li>Container neu erzeugen (<code>up -d --force-recreate</code>) und diese Seite erneut öffnen.</li>
                </ol>
            </div>
        @else
            <div class="mt-4 space-y-3 text-sm">
                <p x-show="!supported" x-cloak class="rounded-xl bg-was p-3 text-wa">Dieser Browser unterstützt kein Web-Push. Auf dem iPhone: iOS 16.4 oder neuer und <b>zuerst</b> „Zum Home-Bildschirm“, dann die App von dort öffnen.</p>
                <p x-show="supported && needsInstall" x-cloak class="rounded-xl bg-was p-3 text-wa">Auf dem iPhone muss LicenseVault erst zum Home-Bildschirm hinzugefügt und <b>von dort geöffnet</b> werden. Danach hier aktivieren.</p>
                <p x-show="denied" x-cloak class="rounded-xl bg-ers p-3 text-er">Benachrichtigungen sind für LicenseVault blockiert. Erlaube sie in den iPhone-Einstellungen unter <b>Mitteilungen → LicenseVault</b> (bzw. in den Website-Einstellungen des Browsers).</p>
                <p x-show="error" x-cloak x-text="error" class="rounded-xl bg-ers p-3 text-er" role="alert"></p>
                <div class="flex flex-wrap gap-2" x-show="supported && !needsInstall" x-cloak>
                    <button type="button" class="btn" x-show="!subscribed" @click="enable()" :disabled="busy"><x-icon name="bell" /> Auf diesem Gerät aktivieren</button>
                    <button type="button" class="btn btn-ghost" x-show="subscribed" x-cloak @click="disable()" :disabled="busy">Auf diesem Gerät deaktivieren</button>
                    <span class="pill pill-ok self-center" x-show="subscribed" x-cloak><x-icon name="check" class="size-4" /> aktiv auf diesem Gerät</span>
                </div>
            </div>

            <h3 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wider text-ink3">Registrierte Geräte</h3>
            @forelse($subscriptions as $sub)
                <div class="flex items-center justify-between border-t border-line py-2.5 text-sm first:border-0">
                    <span><span class="font-medium">{{ $sub->label ?? 'Gerät' }}</span> <span class="text-xs text-ink3">· seit {{ $sub->created_at->format('d.m.Y') }}</span></span>
                    <form method="POST" action="{{ route('push.destroy', $sub) }}" onsubmit="return confirm('Gerät abmelden?')">@csrf @method('DELETE')<button class="text-xs text-ink3 hover:text-er">Entfernen</button></form>
                </div>
            @empty
                <p class="text-sm text-ink2">Noch kein Gerät registriert.</p>
            @endforelse
        @endif
    </section>

    {{-- Gotify --}}
    <section class="card p-6">
        <div class="mb-1 flex items-center justify-between"><h2 class="font-semibold">Gotify (selbst gehosteter Push-Server)</h2>
            <span class="pill {{ $gotifyConfigured ? 'pill-ok' : '' }}">{{ $gotifyConfigured ? 'eingerichtet' : 'nicht eingerichtet' }}</span></div>
        <p class="text-sm text-ink2">Schickt Nachrichten an deinen Gotify-Server; die Gotify-App auf dem Handy zeigt sie an (Android-App offiziell; für iPhone gibt es inoffizielle Gotify-Clients).</p>
        @if($gotifyConfigured)
            <p class="mt-4 text-sm">Server: <span class="font-medium">{{ $gotifyHost }}</span> <span class="text-ink3">· App-Token ist hinterlegt</span></p>
        @else
            <div class="mt-4 rounded-xl bg-panel2 p-4 text-sm text-ink2">
                <p class="font-medium text-ink">Einrichtung (einmalig)</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>In Gotify unter <b>Apps</b> eine App „LicenseVault“ anlegen und das <b>Token</b> kopieren.</li>
                    <li>In die <code>.env</code> eintragen: <code>GOTIFY_URL=https://gotify.deine-domain</code> und <code>GOTIFY_TOKEN=…</code></li>
                    <li>Container neu erzeugen (<code>up -d --force-recreate</code>).</li>
                </ol>
            </div>
        @endif
    </section>
</div>

<section class="card mt-5 max-w-5xl p-6">
    <h2 class="font-semibold">Wann kommen Nachrichten?</h2>
    <p class="mt-1 text-sm text-ink2">Täglich um 06:00 Uhr prüft LicenseVault Ablauf- und Verlängerungsdaten. Für jede neu erreichte Frist ({{ implode(', ', $days) }} Tage vorher sowie am Fälligkeitstag) kommt <b>eine</b> Nachricht – mit Name der Lizenz und Datum, nie mit Schlüsseln oder Passwörtern. Mehr als drei neue Erinnerungen auf einmal werden zusammengefasst. Die Fristen änderst du über <code>REMINDER_DAYS</code> in der <code>.env</code>.</p>
    <form method="POST" action="{{ route('settings.notifications.test') }}" class="mt-4">@csrf
        <button class="btn btn-ghost">Testnachricht senden</button>
    </form>
</section>
@endsection
