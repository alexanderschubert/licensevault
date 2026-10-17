@extends('layouts.app')
@php $edit = $device->exists; @endphp
@section('title', $edit ? 'Gerät bearbeiten' : 'Neues Gerät')
@section('content')
<a href="{{ $edit ? route('homekit.show', $device) : route('homekit.index') }}" class="text-sm text-ink2 hover:text-ac">← Zurück</a>
<h1 class="mt-3 text-2xl font-bold tracking-tight">{{ $edit ? 'Gerät bearbeiten' : 'Neues HomeKit-/Matter-Gerät' }}</h1>

<form method="POST" enctype="multipart/form-data" autocomplete="off" class="card mt-5 max-w-3xl p-6"
      action="{{ $edit ? route('homekit.update', $device) : route('homekit.store') }}"
      x-data="{ proto: '{{ old('protocol', $device->protocol?->value ?? 'homekit') }}' }">
    @csrf @if($edit) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <span class="mb-1.5 block text-sm font-medium">Art des Geräts</span>
            <div class="inline-flex flex-wrap overflow-hidden rounded-xl border border-line" role="radiogroup" aria-label="Art des Geräts">
                @foreach($protocols as $pr)
                    <label class="cursor-pointer px-4 py-2.5 text-sm font-medium transition" :class="proto === '{{ $pr->value }}' ? 'bg-acs text-ac' : 'bg-panel text-ink2 hover:bg-panel2'">
                        <input type="radio" name="protocol" value="{{ $pr->value }}" x-model="proto" class="sr-only"> {{ $pr->label() }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1.5 text-xs text-ink3">@foreach($protocols as $pr)<span x-show="proto === '{{ $pr->value }}'" @if($loop->first === false) x-cloak @endif>{{ $pr->hint() }}</span>@endforeach</p>
            @error('protocol')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2 flex items-center gap-4">
            @if($edit && $device->hasPhoto())<img src="{{ route('homekit.photo', $device) }}?v={{ $device->updated_at->timestamp }}" alt="" class="size-16 shrink-0 rounded-xl object-cover">@else<span class="grid size-16 shrink-0 place-items-center rounded-xl bg-panel2 text-ink3"><x-icon name="qr" class="size-6" /></span>@endif
            <div class="min-w-0 flex-1">
                <label for="photo" class="mb-1.5 block text-sm font-medium">Foto vom Gerät <span class="font-normal text-ink3">(optional, wird vor dem Upload verkleinert)</span></label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="field" onchange="lvShrinkImage(this)">
                @error('photo')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
                @if($edit && $device->hasPhoto())<label class="mt-2 flex items-center gap-2 text-sm text-ink2"><input type="checkbox" name="clear_photo" value="1" class="size-4 accent-[var(--ac)]"> Foto entfernen</label>@endif
            </div>
        </div>
        <div class="sm:col-span-2"><x-field name="name" label="Name *" :value="$device->name" required maxlength="150" /></div>
        <div><x-field name="manufacturer" label="Hersteller" :value="$device->manufacturer" list="mf" maxlength="100" /><datalist id="mf">@foreach($manufacturers as $m)<option value="{{ $m }}">@endforeach</datalist></div>
        <x-field name="model" label="Modell" :value="$device->model" maxlength="100" />
        <div><x-field name="room" label="Raum" :value="$device->room" list="rm" maxlength="60" /><datalist id="rm">@foreach($rooms as $r)<option value="{{ $r }}">@endforeach</datalist></div>
        <div>
            <label for="status" class="mb-1.5 block text-sm font-medium">Status *</label>
            <select id="status" name="status" class="field">@foreach($statuses as $s)<option value="{{ $s->value }}" @selected(old('status', $device->status?->value) === $s->value)>{{ $s->label() }}</option>@endforeach</select>
            @error('status')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2" x-data="hkScanner">
            <button type="button" @click="start()" class="btn w-full sm:w-auto"><x-icon name="qr" /> QR-Code mit Kamera scannen</button>
            <p x-show="note" x-cloak x-text="note" class="mt-2 text-sm text-ok" role="status"></p>

            <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/80 p-4" role="dialog" aria-modal="true" aria-label="QR-Code scannen" @keydown.escape.window="stop()">
                <div class="card w-full max-w-md overflow-hidden p-0">
                    <div class="relative aspect-square bg-black" x-show="live">
                        <video x-ref="video" class="size-full object-cover" muted playsinline></video>
                        <div class="pointer-events-none absolute inset-[12%] rounded-2xl border-2 border-white/80"></div>
                    </div>
                    <div class="space-y-3 p-5 text-sm">
                        <p class="text-ink2" x-show="live">Halte die Kamera auf den HomeKit- oder Matter-QR-Code am Gerät, auf der Verpackung oder in der Anleitung.</p>
                        <p class="text-ink2" x-show="!live" x-cloak>Die Live-Kamera braucht eine <b>HTTPS</b>-Verbindung. Du kannst stattdessen ein Foto vom QR-Code aufnehmen.</p>
                        <p x-show="error" x-cloak x-text="error" class="text-er" role="alert"></p>
                        <div class="flex flex-wrap gap-2">
                            <label class="btn btn-ghost cursor-pointer">Foto aufnehmen
                                <input type="file" accept="image/*" capture="environment" class="sr-only" @change="fromPhoto($event)">
                            </label>
                            <button type="button" class="btn btn-ghost" @click="stop()">Abbrechen</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sm:col-span-2 border-t border-line pt-4 text-xs font-semibold uppercase tracking-wider text-ink3">Kopplungsdaten (verschlüsselt gespeichert)</div>
        <div>
            <label for="setup_code" class="mb-1.5 block text-sm font-medium"><span x-text="proto === 'matter' ? 'Manueller Kopplungscode' : (proto === 'both' ? 'Einrichtungs-/Kopplungscode' : 'Einrichtungscode')">Einrichtungscode</span> @if($edit && $device->hasSetupCode())<span class="font-normal text-ink3">(leer = unverändert)</span>@endif</label>
            <input id="setup_code" name="setup_code" inputmode="numeric" :placeholder="proto === 'matter' ? '{{ $edit && $device->hasSetupCode() ? '••••-•••-••••' : '3497-011-2332' }}' : '{{ $edit && $device->hasSetupCode() ? '•••-••-•••' : '123-45-678' }}'" class="field font-mono">
            <p class="mt-1 text-xs text-ink3" x-show="proto !== 'homekit'" x-cloak>11 Ziffern, steht neben dem QR-Code. Bei bereits gekoppelten Matter-Geräten gilt der Code meist nur einmal – für weitere Systeme in der Home-App einen neuen Code erzeugen.</p>
            @error('setup_code')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
            @if($edit && $device->hasSetupCode())<label class="mt-2 flex items-center gap-2 text-sm text-ink2"><input type="checkbox" name="clear_setup_code" value="1" class="size-4 accent-[var(--ac)]"> Code entfernen</label>@endif
        </div>
        <div>
            <label for="qr_payload" class="mb-1.5 block text-sm font-medium">QR-Payload <span class="font-normal text-ink3">(erzeugt den QR-Code)</span></label>
            <input id="qr_payload" name="qr_payload" :placeholder="proto === 'matter' ? '{{ $edit && $device->hasQrPayload() ? '•••••••••••••••••••' : 'MT:…' }}' : '{{ $edit && $device->hasQrPayload() ? '•••••••••••••••••••' : 'X-HM://…' }}'" class="field font-mono" spellcheck="false">
            @error('qr_payload')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label for="qr_file" class="mb-1.5 block text-sm font-medium">oder QR-Bild hochladen <span class="font-normal text-ink3">(PNG/JPG/WebP/HEIC, max. 2 MB)</span></label>
            <input id="qr_file" name="qr_file" type="file" accept="image/png,image/jpeg,image/webp,image/heic,image/heif,.heic,.heif" class="field" onchange="lvShrinkImage(this, 2400, 0.92)">
            @error('qr_file')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
            @if($edit && $device->hasQr())<label class="mt-2 flex items-center gap-2 text-sm text-ink2"><input type="checkbox" name="clear_qr" value="1" class="size-4 accent-[var(--ac)]"> Gespeicherten QR-Code (Payload und Bild) entfernen</label>@endif
        </div>

        <div class="sm:col-span-2">
            <label for="notes" class="mb-1.5 block text-sm font-medium">Notizen</label>
            <textarea id="notes" name="notes" rows="3" maxlength="5000" class="field">{{ old('notes', $device->notes) }}</textarea>
        </div>
    </div>
    <div class="mt-6 flex justify-end gap-2">
        <a href="{{ $edit ? route('homekit.show', $device) : route('homekit.index') }}" class="btn btn-ghost">Abbrechen</a>
        <button class="btn">{{ $edit ? 'Speichern' : 'Gerät anlegen' }}</button>
    </div>
</form>
@endsection
