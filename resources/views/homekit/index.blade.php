@extends('layouts.app')
@section('title', 'HomeKit & Matter')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-2xl font-bold tracking-tight">HomeKit &amp; Matter</h1><p class="mt-1 text-ink2">{{ $total }} {{ $total === 1 ? 'Gerät' : 'Geräte' }} · Codes nur nach Anmeldung sichtbar</p></div>
    <div class="flex gap-2">
        <a href="{{ route('homekit.print.pdf', $filters) }}" class="btn btn-ghost">PDF</a>
        <a href="{{ route('homekit.print', $filters) }}" target="_blank" class="btn btn-ghost">Druckansicht</a>
        <a href="{{ route('homekit.create', ['scan' => 1]) }}" class="btn btn-ghost"><x-icon name="qr" /> Scannen</a>
        <a href="{{ route('homekit.create') }}" class="btn"><x-icon name="plus" /> Neues Gerät</a>
    </div>
</div>
<div class="mt-5"><x-flash /></div>

<div class="mb-4 flex flex-wrap gap-2">
    @php $base = array_filter(['q' => $filters['q'] ?? null, 'status' => $filters['status'] ?? null, 'protocol' => $filters['protocol'] ?? null]); @endphp
    <a href="{{ route('homekit.index', $base) }}" class="chip {{ empty($filters['room']) ? 'chip-on' : '' }}">Alle · {{ $total }}</a>
    @foreach ($rooms as $r)<a href="{{ route('homekit.index', $base + ['room' => $r]) }}" class="chip {{ ($filters['room'] ?? '') === $r ? 'chip-on' : '' }}">{{ $r }}</a>@endforeach
</div>

<form method="GET" class="mb-5 flex flex-wrap gap-3">
    @if(!empty($filters['room']))<input type="hidden" name="room" value="{{ $filters['room'] }}">@endif
    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, Hersteller, Modell, Raum…" class="field max-w-md" aria-label="Suche">
    <select name="protocol" class="field w-auto" aria-label="Standard"><option value="">Alle</option>
        <option value="homekit" @selected(($filters['protocol'] ?? '') === 'homekit')>HomeKit</option>
        <option value="matter" @selected(($filters['protocol'] ?? '') === 'matter')>Matter</option></select>
    <select name="status" class="field w-auto" aria-label="Status"><option value="">Jeder Status</option>
        @foreach (\App\Enums\HomekitStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select>
    <button class="btn btn-ghost">Filtern</button>
</form>

@if ($devices->isEmpty())
    <div class="card grid place-items-center p-16 text-center text-ink2">{{ $total ? 'Keine Geräte für diesen Filter.' : 'Noch keine Geräte. Lege dein erstes HomeKit-Gerät an.' }}</div>
@else
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
    @foreach ($devices as $d)
        <article class="card group relative flex flex-col gap-3 p-5 transition hover:border-ac" x-data="qrCard">
            <div class="flex items-start gap-3.5">
                {{-- Gerätefoto (Platzhalter ohne Foto) --}}
                <a href="{{ route('homekit.show', $d) }}" class="relative grid size-[88px] shrink-0 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-acs to-panel2 text-ac/60" aria-label="{{ $d->name }} – Details">
                    <x-icon name="home" class="size-9" />
                    @if($d->hasPhoto())
                        <img src="{{ route('homekit.photo', $d) }}?v={{ $d->updated_at->timestamp }}" alt="" loading="lazy" onerror="this.style.display='none'" class="absolute inset-0 size-full object-cover">
                    @endif
                </a>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('homekit.show', $d) }}" class="block truncate font-semibold hover:text-ac">{{ $d->name }}</a>
                    <span class="block truncate text-xs text-ink3">{{ $d->manufacturer }}{{ $d->manufacturer && $d->model ? ' · ' : '' }}{{ $d->model }}</span>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($d->protocol->parts() as $part)<span class="pill {{ $part->pill() }}">{{ $part->label() }}</span>@endforeach
                        <span class="pill {{ $d->status->pill() }}">{{ $d->status->label() }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-auto flex items-center gap-2 border-t border-line pt-3">
                @if($d->room)<span class="mr-auto min-w-0 truncate rounded-full bg-panel2 px-2.5 py-1 text-xs font-medium text-ink2">{{ $d->room }}</span>@else<span class="mr-auto"></span>@endif
                @if($d->hasQr())
                    <button type="button" @click="toggle()" class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl bg-ac px-3 py-2 text-xs font-semibold text-ac-tx hover:brightness-110"><x-icon name="qr" class="size-4" /> QR-Code</button>
                @endif
                <a href="{{ route('homekit.show', $d) }}" class="grid size-9 shrink-0 place-items-center rounded-xl border border-line text-ink2 hover:bg-panel2 hover:text-ac" aria-label="{{ $d->name }} – Details" title="Details"><x-icon name="eye" class="size-4" /></a>
                <a href="{{ route('homekit.edit', $d) }}" class="grid size-9 shrink-0 place-items-center rounded-xl border border-line text-ink2 hover:bg-panel2 hover:text-ac" aria-label="{{ $d->name }} bearbeiten" title="Bearbeiten"><x-icon name="edit" class="size-4" /></a>
            </div>

            {{-- QR-Code erst auf Klick laden und groß anzeigen --}}
            @if($d->hasQr())
            <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="QR-Code {{ $d->name }}"
                 @keydown.escape.window="open && toggle()" @click.self="toggle()">
                <div class="card w-full max-w-sm p-5 text-center">
                    <div class="mx-auto aspect-square w-full max-w-[300px] rounded-xl bg-white p-3">
                        <img :src="open ? '{{ route('homekit.qr', $d) }}' : ''" alt="QR-Code {{ $d->name }}" class="size-full object-contain">
                    </div>
                    <p class="mt-3 font-semibold">{{ $d->name }}</p>
                    <p class="text-xs text-ink3">{{ collect($d->protocol->parts())->map->label()->implode(' + ') }} · schließt automatisch nach 60 Sekunden</p>
                    <button type="button" class="btn btn-ghost mt-4 w-full" @click="toggle()">Schließen</button>
                </div>
            </div>
            @endif
        </article>
    @endforeach
</div>
@endif
@endsection
