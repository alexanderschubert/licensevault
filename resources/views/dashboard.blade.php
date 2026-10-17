@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<section class="hero-bg rounded-3xl p-6 sm:p-10">
    <span class="blob"></span>
    <div class="glass max-w-2xl rounded-2xl p-7">
        <p class="text-sm font-medium text-ink2">{{ now()->translatedFormat('l, j. F Y') }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight">Willkommen zurück, {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-ink2">Hier laufen bald alle Lizenzen, Abos und HomeKit-Codes zusammen.</p>
        <div class="mt-5 flex flex-wrap gap-2">
            <span class="pill {{ $dbOk ? 'pill-ok' : 'pill-er' }}"><span class="size-1.5 rounded-full bg-current"></span>{{ $dbOk ? 'Datenbank verbunden' : 'Datenbank nicht erreichbar' }}</span>
            <span class="pill pill-ac">Version 0.1.0</span>
        </div>
    </div>
</section>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @php
        $kpis = [
            ['Aktive Lizenzen', 'key', (string) $activeCount, 'ohne abgelaufene', route('licenses.index', ['status' => 'active'])],
            ['Laufende Kosten pro Jahr', 'doc', number_format($yearlyCost, 2, ',', '.').' €', '≈ '.number_format($yearlyCost / 12, 2, ',', '.').' € pro Monat (EUR)', route('licenses.index', ['status' => 'active'])],
            ['Läuft bald ab', 'bell', (string) $soonCount, 'in den nächsten '.\App\Models\License::SOON_DAYS.' Tagen'.($overdueCount ? ' · '.$overdueCount.' überfällig' : ''), route('licenses.index', ['status' => 'soon'])],
            ['HomeKit-/Matter-Geräte', 'qr', (string) $homekitCount, 'in der QR-Galerie', route('homekit.index')],
        ];
    @endphp
    @foreach ($kpis as [$label, $icon, $value, $hint, $href])
        <a href="{{ $href }}" class="card block p-5 transition hover:border-ac">
            <div class="flex items-center justify-between text-sm font-medium text-ink2">{{ $label }}<span class="grid size-9 place-items-center rounded-xl bg-acs text-ac"><x-icon :name="$icon" /></span></div>
            <div class="mt-3 text-3xl font-bold tracking-tight {{ $value === '—' ? 'text-ink3' : '' }}">{{ $value }}</div>
            <div class="mt-1 text-xs text-ink3">{{ $hint }}</div>
        </a>
    @endforeach
</div>

<div class="card mt-4 p-5">
    <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold">Bald fällig</h2><a href="{{ route('reminders.index') }}" class="text-xs font-medium text-ac">Erinnerungen →</a></div>
    @forelse ($dueItems->take(6) as $item)
        @php
            $l = $item['license']; $d = $item['days']; $renewal = $item['kind'] === 'renewal';
            $label = match (true) {
                $d < 0 => ($renewal ? 'Verlängerung überfällig seit ' : 'abgelaufen seit ').abs($d).' '.(abs($d) === 1 ? 'Tag' : 'Tagen'),
                $d === 0 => ($renewal ? 'Verlängerung heute' : 'läuft heute ab'),
                default => ($renewal ? 'Verlängerung in ' : 'läuft ab in ').$d.' '.($d === 1 ? 'Tag' : 'Tagen'),
            };
            $class = $d <= 0 ? 'pill-er' : ($d <= 7 ? 'pill-wa' : 'pill-ac');
        @endphp
        <a href="{{ route('licenses.show', $l) }}" class="flex items-center justify-between gap-3 border-t border-line py-2.5 first:border-0 hover:text-ac">
            <span class="min-w-0 truncate"><span class="font-semibold">{{ $l->name }}</span> <span class="text-xs text-ink3">{{ $l->vendor }} · {{ $item['date']->format('d.m.Y') }}</span></span>
            <span class="pill shrink-0 {{ $class }}">{{ $label }}</span>
        </a>
    @empty
        <p class="text-sm text-ink2">Nichts läuft in den nächsten {{ \App\Models\License::SOON_DAYS }} Tagen ab und nichts muss verlängert werden.</p>
    @endforelse
</div>

<div class="card mt-4 p-5">
    <h2 class="mb-3 font-semibold">Zuletzt geändert</h2>
    @forelse ($recent as [$type, $name, $href, $at])
        <a href="{{ $href }}" class="flex items-center justify-between gap-3 border-t border-line py-2.5 first:border-0 hover:text-ac">
            <span class="flex min-w-0 items-center gap-2"><span class="pill">{{ $type }}</span><span class="truncate font-medium">{{ $name }}</span></span>
            <span class="shrink-0 text-xs text-ink3">{{ $at->diffForHumans() }}</span>
        </a>
    @empty
        <p class="text-sm text-ink2">Noch keine Einträge.</p>
    @endforelse
</div>
@endsection
