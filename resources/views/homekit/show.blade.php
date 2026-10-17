@extends('layouts.app')
@section('title', $device->name)
@section('content')
<a href="{{ route('homekit.index') }}" class="text-sm text-ink2 hover:text-ac">← Alle Geräte</a>
<div class="mt-3 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">{{ $device->name }}</h1>
        <p class="mt-1 text-ink2">{{ $device->manufacturer }}{{ $device->manufacturer && $device->model ? ' · ' : '' }}{{ $device->model }}</p>
        <div class="mt-3 flex gap-2">@foreach($device->protocol->parts() as $part)<span class="pill {{ $part->pill() }}">{{ $part->label() }}</span>@endforeach<span class="pill {{ $device->status->pill() }}">{{ $device->status->label() }}</span>@if($device->room)<span class="pill">{{ $device->room }}</span>@endif</div>
    </div>
    <div class="flex gap-2" x-data="{ confirm: false }">
        @if($device->hasQr())<button type="button" class="btn btn-ghost" onclick="lvPrint('{{ route('homekit.pdf', $device) }}')">Drucken</button><a href="{{ route('homekit.pdf', $device) }}" class="btn btn-ghost">PDF</a>@endif
        <a href="{{ route('homekit.edit', $device) }}" class="btn btn-ghost"><x-icon name="edit" /> Bearbeiten</a>
        <button type="button" class="btn btn-ghost !text-er" @click="confirm = true"><x-icon name="trash" /> Löschen</button>
        <div x-show="confirm" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" @keydown.escape.window="confirm = false" role="dialog" aria-modal="true">
            <div class="card w-full max-w-sm p-6" @click.outside="confirm = false">
                <h2 class="text-lg font-bold">Gerät löschen?</h2>
                <p class="mt-2 text-sm text-ink2">„{{ $device->name }}“ wird mit QR-Code und Einrichtungscode unwiderruflich gelöscht.</p>
                <form method="POST" action="{{ route('homekit.destroy', $device) }}" class="mt-5 flex justify-end gap-2">@csrf @method('DELETE')
                    <button type="button" class="btn btn-ghost" @click="confirm = false">Abbrechen</button><button class="btn btn-danger">Endgültig löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="mt-5"><x-flash /></div>

<div class="mt-2 grid gap-5 lg:grid-cols-[360px_1fr]">
    <div class="space-y-5">
    @if($device->hasPhoto())<div class="card overflow-hidden p-0"><img src="{{ route('homekit.photo', $device) }}?v={{ $device->updated_at->timestamp }}" alt="Foto: {{ $device->name }}" onerror="this.parentElement.style.display='none'" class="max-h-72 w-full object-cover"></div>@endif
    <div class="card h-fit p-5" x-data="qrCard">
        <div class="relative mx-auto grid aspect-square w-full max-w-[300px] place-items-center overflow-hidden rounded-xl border border-line bg-white p-3">
            @if($device->hasQr())
                <img x-show="open" x-cloak :src="open ? '{{ route('homekit.qr', $device) }}' : ''" alt="QR-Code {{ $device->name }}" class="size-full object-contain">
                <button type="button" x-show="!open" @click="toggle()" class="absolute inset-0 grid place-items-center bg-[repeating-conic-gradient(#d9dbe8_0_25%,#fff_0_50%)] bg-[length:22px_22px]">
                    <span class="flex flex-col items-center gap-1.5 rounded-xl bg-white/90 px-4 py-3 text-sm font-semibold text-[#14152b]"><x-icon name="lock" class="size-5" />QR-Code anzeigen</span></button>
            @else<span class="text-sm text-ink3">Kein QR-Code hinterlegt</span>@endif
        </div>
        @if($device->hasQr())<button type="button" x-show="open" x-cloak @click="toggle()" class="mt-3 w-full text-center text-sm font-medium text-ac">Verbergen</button>@endif
    </div>
    </div>

    <div class="space-y-5">
        <div class="card p-6" x-data="secretBox('{{ route('homekit.code', $device) }}')">
            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink3"><x-icon name="lock" class="size-4" /> {{ $device->protocol->codeLabel() }} · verschlüsselt</div>
            @if($device->hasSetupCode())
                <code class="block rounded-xl bg-panel2 px-3.5 py-3 font-mono text-lg tracking-widest" x-text="value ?? '{{ $device->protocol->value === 'matter' ? '••••-•••-••••' : '•••-••-•••' }}'"></code>
                <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-er"></p>
                <div class="mt-3 flex gap-2">
                    <button type="button" class="btn btn-ghost !py-2" @click="value ? hide() : show()"><x-icon name="eye" /><span x-text="value ? 'Verbergen' : 'Anzeigen (30 s)'"></span></button>
                    <button type="button" class="btn btn-ghost !py-2" x-show="value" x-cloak @click="copy()"><x-icon name="copy" /><span x-text="copied ? 'Kopiert' : 'Kopieren'"></span></button>
                </div>
            @else<p class="text-sm text-ink2">Kein {{ $device->protocol->codeLabel() }} hinterlegt.</p>@endif
        </div>
        <x-attachments :model="$device" key="homekit" />
        @if($device->notes)<div class="card p-6"><div class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink3">Notizen</div><p class="whitespace-pre-line text-sm">{{ $device->notes }}</p></div>@endif
    </div>
</div>
@endsection
