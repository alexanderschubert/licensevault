@extends('layouts.app')
@section('title', 'Backup & Export')
@section('content')
<h1 class="text-2xl font-bold tracking-tight">Backup &amp; Export</h1>
<p class="mt-1 text-ink2">Verschlüsselte Sicherungen von Datenbank und Uploads · täglich um {{ $time }} Uhr, die letzten {{ $keep }} werden behalten</p>
<div class="mt-5"><x-flash />
    @error('backup')<div class="pill pill-er mb-4 !px-3.5 !py-2 text-sm" role="alert">{{ $message }}</div>@enderror</div>

@unless($configured)
    <div class="card mb-5 border-wa p-5 text-sm"><b class="text-wa">Backups sind noch nicht eingerichtet.</b> Setze in der <code>.env</code> eine <code>BACKUP_PASSPHRASE</code> (mind. 12 Zeichen) und starte die Container neu. Ohne Passphrase werden keine Sicherungen erstellt.</div>
@endunless

<div class="mb-5 grid gap-5 lg:grid-cols-[1fr_340px]">
    <div class="card overflow-x-auto">
        <div class="flex items-center justify-between px-5 py-4"><h2 class="font-semibold">Sicherungen</h2>
            <form method="POST" action="{{ route('backup.create') }}">@csrf<button class="btn !py-2" @disabled(!$configured)>Jetzt sichern</button></form></div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-line border-t border-line">
            @forelse($backups as $b)
                <tr>
                    <td class="px-5 py-3"><span class="font-medium">{{ $b['name'] }}</span>@if(str_contains($b['name'], 'pre-restore'))<span class="pill pill-wa ml-2">vor Restore</span>@endif
                        <div class="text-xs text-ink3">{{ \Illuminate\Support\Carbon::createFromTimestamp($b['time'])->format('d.m.Y H:i') }} · {{ number_format($b['size'] / 1024, 0, ',', '.') }} KB</div></td>
                    <td class="px-5 py-3"><div class="flex justify-end gap-2">
                        <form method="POST" action="{{ route('backup.verify', $b['name']) }}">@csrf<button class="btn btn-ghost !py-1.5 !text-xs">Prüfen</button></form>
                        <a href="{{ route('backup.download', $b['name']) }}" class="btn btn-ghost !py-1.5 !text-xs">Download</a>
                        <form method="POST" action="{{ route('backup.destroy', $b['name']) }}" onsubmit="return confirm('Sicherung löschen?')">@csrf @method('DELETE')<button class="btn btn-ghost !py-1.5 !text-xs !text-er">Löschen</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td class="px-5 py-12 text-center text-ink2">Noch keine Sicherung vorhanden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-5">
        <div class="card p-5">
            <h2 class="mb-2 font-semibold">CSV-Export</h2>
            <p class="mb-3 text-sm text-ink2">Ohne Lizenzschlüssel, Einrichtungscodes und QR-Daten.</p>
            <div class="flex flex-col gap-2"><a class="btn btn-ghost" href="{{ route('export.licenses') }}"><x-icon name="doc" /> Lizenzen (CSV)</a><a class="btn btn-ghost" href="{{ route('export.homekit') }}"><x-icon name="doc" /> HomeKit-Geräte (CSV)</a></div>
        </div>
        <div class="card p-5 text-sm text-ink2">
            <h2 class="mb-2 font-semibold text-ink">Wiederherstellen</h2>
            <p>Restore läuft bewusst nur per Kommandozeile (mit Vorschau, Bestätigung und automatischer Sicherheitskopie):</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-panel2 p-3 text-xs text-ink">docker compose exec -u app app \
  php artisan licensevault:restore &lt;datei&gt; --dry-run</pre>
            <p class="mt-3">Sichere <b>APP_KEY</b> und <b>BACKUP_PASSPHRASE</b> getrennt von den Sicherungen – ohne sie ist kein Restore möglich.</p>
        </div>
    </div>
</div>
@endsection
