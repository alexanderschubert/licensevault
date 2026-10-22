@extends('layouts.app')
@section('title', 'Lizenzen')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-2xl font-bold tracking-tight">Lizenzen</h1><p class="mt-1 text-ink2">{{ $total }} {{ $total === 1 ? 'Lizenz' : 'Lizenzen' }} · Schlüssel verschlüsselt gespeichert</p></div>
    <div class="flex flex-wrap gap-2">
        <div class="inline-flex overflow-hidden rounded-xl border border-line bg-panel text-sm" role="group" aria-label="Ansicht">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => null]) }}" class="px-3.5 py-2.5 font-medium {{ $view === 'list' ? 'bg-acs text-ac' : 'text-ink2 hover:bg-panel2' }}" @if($view === 'list') aria-current="true" @endif>Liste</a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'tiles', 'page' => null]) }}" class="border-l border-line px-3.5 py-2.5 font-medium {{ $view === 'tiles' ? 'bg-acs text-ac' : 'text-ink2 hover:bg-panel2' }}" @if($view === 'tiles') aria-current="true" @endif>Kacheln</a>
        </div>
        <a href="{{ route('export.licenses') }}" class="btn btn-ghost">CSV-Export</a><a href="{{ route('licenses.create') }}" class="btn"><x-icon name="plus" /> Neue Lizenz</a></div>
</div>

<div class="mt-5"><x-flash /></div>

<form method="GET" class="mb-4 flex flex-wrap gap-3">
    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, Hersteller, Tag, Gerät oder Notiz suchen…" class="field min-w-[240px] flex-1" aria-label="Suche">
    <select name="type" class="field w-auto" aria-label="Typ"><option value="">Alle Typen</option>
        @foreach (\App\Enums\LicenseType::cases() as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>@endforeach</select>
    <select name="interval" class="field w-auto" aria-label="Intervall"><option value="">Jedes Intervall</option>
        @foreach (\App\Enums\BillingInterval::cases() as $i)<option value="{{ $i->value }}" @selected(($filters['interval'] ?? '') === $i->value)>{{ $i->label() }}</option>@endforeach</select>
    <select name="platform" class="field w-auto" aria-label="Plattform"><option value="">Alle Plattformen</option>
        @foreach (\App\Enums\Platform::cases() as $pl)<option value="{{ $pl->value }}" @selected(($filters['platform'] ?? '') === $pl->value)>{{ $pl->label() }}</option>@endforeach</select>
    <select name="device" class="field w-auto" aria-label="Gerät"><option value="">Alle Geräte</option>
        @foreach ($devices as $dv)<option value="{{ $dv->id }}" @selected(($filters['device'] ?? '') == $dv->id)>{{ $dv->name }}</option>@endforeach</select>
    <select name="category" class="field w-auto" aria-label="Kategorie"><option value="">Alle Kategorien</option>
        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select>
    <select name="tag" class="field w-auto" aria-label="Tag"><option value="">Alle Tags</option>
        @foreach ($tags as $t)<option value="{{ $t->id }}" @selected(($filters['tag'] ?? '') == $t->id)>#{{ $t->name }}</option>@endforeach</select>
    <select name="status" class="field w-auto" aria-label="Status"><option value="">Jeder Status</option>
        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktiv</option>
        <option value="soon" @selected(($filters['status'] ?? '') === 'soon')>Läuft bald ab</option>
        <option value="expired" @selected(($filters['status'] ?? '') === 'expired')>Abgelaufen</option></select>
    <input type="hidden" name="view" value="{{ $view }}">
    <button class="btn btn-ghost">Filtern</button>
</form>

@if($view === 'tiles')
@include('licenses._tiles')
@else
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-panel2 text-left text-[11px] uppercase tracking-wider text-ink3">
            <tr><th class="px-4 py-3">Lizenz</th><th class="px-4 py-3">Typ</th><th class="px-4 py-3">Zugang</th><th class="px-4 py-3">Läuft ab</th><th class="px-4 py-3">Geräte</th><th class="px-4 py-3 text-right">Preis</th><th class="px-4 py-3"><span class="sr-only">Aktionen</span></th></tr>
        </thead>
        <tbody class="divide-y divide-line">
        @forelse ($licenses as $l)
            @php $st = $l->expiryStatus(); @endphp
            <tr class="cursor-pointer hover:bg-panel2/60" onclick="if(!event.target.closest('a,button')) location.href='{{ route('licenses.show', $l) }}'">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3"><x-license-icon :license="$l" size="size-9" />
                    <div class="min-w-0"><a href="{{ route('licenses.show', $l) }}" class="block font-semibold hover:text-ac">{{ $l->name }}</a>
                    <span class="text-xs text-ink3">{{ $l->vendor }}{{ $l->vendor && $l->product_version ? ' · ' : '' }}{{ $l->product_version }}@if($l->platformEnums()) · {{ collect($l->platformEnums())->map->label()->implode(', ') }}@endif</span></div></div>
                </td>
                <td class="px-4 py-3"><span class="pill {{ $l->license_type->pill() }}">{{ $l->license_type->label() }}</span></td>
                <td class="px-4 py-3"><div class="flex flex-wrap gap-1.5">@if($l->hasSecret())<span class="inline-flex items-center gap-1.5 rounded-lg bg-panel2 px-2.5 py-1 text-xs text-ink2"><x-icon name="lock" class="size-3.5" />{{ $l->keys->count() > 1 ? $l->keys->count().' Schlüssel' : 'Schlüssel' }}</span>@endif @if($l->hasAccount())<span class="inline-flex items-center gap-1.5 rounded-lg bg-panel2 px-2.5 py-1 text-xs text-ink2"><x-icon name="key" class="size-3.5" />Konto</span>@endif @unless($l->hasSecret() || $l->hasAccount())<span class="text-ink3">—</span>@endunless</div></td>
                <td class="px-4 py-3"><span class="pill {{ $st['class'] }}">{{ $st['label'] }}</span></td>
                <td class="px-4 py-3 text-ink2">{{ $l->device_limit ?? '—' }}</td>
                <td class="px-4 py-3 text-right font-semibold">{{ $l->priceLabel() }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('licenses.edit', $l) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1.5 text-xs font-medium text-ink2 hover:bg-panel2 hover:text-ac" aria-label="{{ $l->name }} bearbeiten"><x-icon name="edit" class="size-3.5" />Bearbeiten</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-14 text-center text-ink2">
                {{ $total ? 'Keine Lizenzen für diesen Filter.' : 'Noch keine Lizenzen. Lege deine erste an.' }}
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
<div class="mt-4">{{ $licenses->links() }}</div>
@endsection
