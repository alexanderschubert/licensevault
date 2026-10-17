@extends('layouts.app')
@section('title', 'Dokumente')
@section('content')
<h1 class="text-2xl font-bold tracking-tight">Dokumente</h1>
<p class="mt-1 text-ink2">{{ $total }} {{ $total === 1 ? 'Datei' : 'Dateien' }} · nur nach Anmeldung abrufbar</p>
<div class="mt-5"><x-flash /></div>

<div class="card mb-5 p-5">
    <x-upload-form>
        <div class="min-w-[220px] flex-1">
            <label class="mb-1.5 block text-sm font-medium" for="attach">Zuordnen zu <span class="font-normal text-ink3">(optional)</span></label>
            <select id="attach" name="attach" class="field"><option value="">— keine —</option>
                <optgroup label="Lizenzen">@foreach($licenses as $l)<option value="license:{{ $l->id }}">{{ $l->name }}</option>@endforeach</optgroup>
                <optgroup label="HomeKit">@foreach($devices as $d)<option value="homekit:{{ $d->id }}">{{ $d->name }}</option>@endforeach</optgroup></select>
        </div>
    </x-upload-form>
</div>

<form method="GET" class="mb-4 flex gap-3"><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Titel oder Dateiname suchen…" class="field max-w-md" aria-label="Suche"><button class="btn btn-ghost">Suchen</button></form>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-panel2 text-left text-[11px] uppercase tracking-wider text-ink3"><tr><th class="px-4 py-3">Dokument</th><th class="px-4 py-3">Zugeordnet</th><th class="px-4 py-3">Größe</th><th class="px-4 py-3">Hochgeladen</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-line">
        @forelse($documents as $d)
            <tr>
                <td class="px-4 py-3"><a href="{{ route('documents.download', $d) }}" class="block font-semibold hover:text-ac">{{ $d->title }}</a><span class="text-xs text-ink3">{{ $d->original_name }}</span></td>
                <td class="px-4 py-3">@if($d->attachable)<a href="{{ $d->attachable instanceof \App\Models\License ? route('licenses.show', $d->attachable) : route('homekit.show', $d->attachable) }}" class="pill pill-ac">{{ $d->attachable->name }}</a>@else<span class="text-ink3">—</span>@endif</td>
                <td class="px-4 py-3 text-ink2">{{ $d->sizeLabel() }}</td>
                <td class="px-4 py-3 text-ink2">{{ $d->created_at->format('d.m.Y') }}</td>
                <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('documents.destroy', $d) }}" onsubmit="return confirm('Dokument löschen?')">@csrf @method('DELETE')<button class="text-ink3 hover:text-er" aria-label="Löschen"><x-icon name="trash" /></button></form></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-14 text-center text-ink2">{{ $total ? 'Keine Treffer.' : 'Noch keine Dokumente hochgeladen.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $documents->links() }}</div>
@endsection
