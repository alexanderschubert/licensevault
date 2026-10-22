@extends('layouts.app')
@php $edit = $license->exists; $v = fn ($k, $d = '') => old($k, $d); @endphp
@section('title', $edit ? 'Lizenz bearbeiten' : 'Neue Lizenz')
@section('content')
<a href="{{ $edit ? route('licenses.show', $license) : route('licenses.index') }}" class="text-sm text-ink2 hover:text-ac">← Zurück</a>
<h1 class="mt-3 text-2xl font-bold tracking-tight">{{ $edit ? 'Lizenz bearbeiten' : 'Neue Lizenz' }}</h1>

<form method="POST" action="{{ $edit ? route('licenses.update', $license) : route('licenses.store') }}" enctype="multipart/form-data" class="card mt-5 max-w-3xl p-6" autocomplete="off">
    @csrf @if($edit) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2"><x-field name="name" label="Name *" :value="$license->name" required maxlength="150" /></div>
        <div class="sm:col-span-2 flex items-center gap-4">
            <x-license-icon :license="$license" size="size-16" />
            <div class="flex-1">
                <label for="icon" class="mb-1.5 block text-sm font-medium">Icon <span class="font-normal text-ink3">(PNG/JPG/WebP/HEIC, max. 512 KB, optional)</span></label>
                <input id="icon" name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/heic,image/heif,.heic,.heif" class="field" onchange="lvShrinkImage(this, 512, 0.9)">
                @error('icon')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
                @if($edit && $license->hasIcon())<label class="mt-2 flex items-center gap-2 text-sm text-ink2"><input type="checkbox" name="clear_icon" value="1" class="size-4 accent-[var(--ac)]"> Icon entfernen</label>@endif
            </div>
        </div>
        <x-field name="vendor" label="Hersteller" :value="$license->vendor" maxlength="150" />
        <x-field name="product_version" label="Version" :value="$license->product_version" maxlength="50" />
        <div>
            <label for="license_type" class="mb-1.5 block text-sm font-medium">Lizenztyp *</label>
            <select id="license_type" name="license_type" class="field">
                @foreach($types as $t)<option value="{{ $t->value }}" @selected(old('license_type', $license->license_type?->value) === $t->value)>{{ $t->label() }}</option>@endforeach
            </select>
            @error('license_type')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>
        <fieldset class="sm:col-span-2">
            <legend class="mb-1.5 text-sm font-medium">Plattform</legend>
            <div class="flex flex-wrap gap-2">
                @php $selected = old('platforms', $license->platforms ?? []); @endphp
                @foreach($platformOptions as $pl)
                    <label class="chip flex cursor-pointer items-center gap-2 has-[:checked]:border-ac has-[:checked]:bg-acs has-[:checked]:text-ac">
                        <input type="checkbox" name="platforms[]" value="{{ $pl->value }}" @checked(in_array($pl->value, (array) $selected, true)) class="size-4 accent-[var(--ac)]"> {{ $pl->label() }}
                    </label>
                @endforeach
            </div>
            @error('platforms.*')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </fieldset>
        <div class="sm:col-span-2">
            <label for="devices" class="mb-1.5 block text-sm font-medium">Installiert auf <span class="font-normal text-ink3">(Geräte, Komma-getrennt, z. B. MacBook Pro, iPhone)</span></label>
            <input id="devices" name="devices" list="devs" value="{{ old('devices', $license->devices->pluck('name')->implode(', ')) }}" class="field" maxlength="500">
            <datalist id="devs">@foreach($deviceNames as $dn)<option value="{{ $dn }}">@endforeach</datalist>
            @error('devices')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>
        <x-field name="device_limit" label="Gerätelimit" type="number" :value="$license->device_limit" min="1" />

        @php
            $keyRows = old('keys') !== null
                ? collect(old('keys'))->map(fn ($k) => ['id' => $k['id'] ?? '', 'label' => $k['label'] ?? '', 'saved' => filled($k['id'] ?? null), 'remove' => ! empty($k['remove'])])->values()
                : $license->keys->map(fn ($k) => ['id' => $k->id, 'label' => $k->label ?? '', 'saved' => true, 'remove' => false])->values();
        @endphp
        <div class="sm:col-span-2" x-data="{ rows: @js($keyRows), add() { this.rows.push({ id: '', label: '', saved: false, remove: false }) } }">
            <span class="mb-1.5 block text-sm font-medium">Lizenzschlüssel / -nummern <span class="font-normal text-ink3">(optional, verschlüsselt – z. B. je Modul oder Funktionsumfang)</span></span>
            <div class="space-y-3">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="rounded-xl border border-line p-3" :class="row.remove && 'opacity-50'">
                        <input type="hidden" :name="`keys[${i}][id]`" :value="row.id">
                        <div class="grid gap-2 sm:grid-cols-[1fr_2fr]">
                            <input :name="`keys[${i}][label]`" x-model="row.label" maxlength="100" class="field" placeholder="Bezeichnung, z. B. Pro-Modul" aria-label="Bezeichnung des Schlüssels">
                            <input :name="`keys[${i}][secret]`" maxlength="5000" autocomplete="off" spellcheck="false" class="field font-mono" :placeholder="row.saved ? '•••••••••••• (leer = unverändert)' : 'XXXX-XXXX-XXXX-XXXX'" aria-label="Lizenzschlüssel">
                        </div>
                        <label class="mt-2 flex items-center gap-2 text-sm text-ink2"><input type="checkbox" :name="`keys[${i}][remove]`" value="1" x-model="row.remove" class="size-4 accent-[var(--ac)]"> <span x-text="row.saved ? 'Diesen Schlüssel entfernen' : 'Diese Zeile verwerfen'"></span></label>
                    </div>
                </template>
            </div>
            <button type="button" class="btn btn-ghost mt-3 !py-2" @click="add()">+ Weiteren Schlüssel hinzufügen</button>
            @error('keys.*')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2 border-t border-line pt-4">
            <div class="text-xs font-semibold uppercase tracking-wider text-ink3">Konto-Zugang <span class="font-normal normal-case">(optional – für Programme mit Login statt Schlüssel, z. B. Abo-Konto)</span></div>
        </div>
        <x-field name="account_login" label="Benutzername / E-Mail" :value="$license->account_login" maxlength="190" autocomplete="off" />
        <x-field name="account_url" label="Anmelde-Adresse (URL)" type="url" :value="$license->account_url" maxlength="255" placeholder="https://…" />
        <div class="sm:col-span-2">
            <label for="account_password" class="mb-1.5 block text-sm font-medium">Konto-Passwort @if($edit && $license->hasAccountPassword())<span class="font-normal text-ink3">(leer lassen = unverändert)</span>@endif</label>
            <input id="account_password" name="account_password" type="password" autocomplete="new-password" placeholder="{{ $edit && $license->hasAccountPassword() ? '••••••••••••' : '' }}" class="field">
            @error('account_password')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
            @if($edit && $license->hasAccountPassword())<label class="mt-2 flex items-center gap-2 text-sm text-ink2"><input type="checkbox" name="clear_account_password" value="1" class="size-4 accent-[var(--ac)]"> Gespeichertes Passwort entfernen</label>@endif
        </div>

        <x-field name="customer_number" label="Kundennummer" :value="$license->customer_number" maxlength="100" />
        <x-field name="support_contact" label="Support-Kontakt (E-Mail, Telefon oder URL)" :value="$license->support_contact" maxlength="190" />
        <div class="sm:col-span-2">
            <label for="recovery_codes" class="mb-1.5 block text-sm font-medium">2FA-/Wiederherstellungscodes <span class="font-normal text-ink3">(einer pro Zeile, verschlüsselt){{ $edit && $license->hasRecoveryCodes() ? ' – leer lassen = unverändert' : '' }}</span></label>
            <textarea id="recovery_codes" name="recovery_codes" rows="3" maxlength="5000" spellcheck="false" autocomplete="off" class="field font-mono" placeholder="{{ $edit && $license->hasRecoveryCodes() ? '••••••••' : '' }}"></textarea>
            @error('recovery_codes')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
            @if($edit && $license->hasRecoveryCodes())<label class="mt-2 flex items-center gap-2 text-sm text-ink2"><input type="checkbox" name="clear_recovery_codes" value="1" class="size-4 accent-[var(--ac)]"> Gespeicherte Codes entfernen</label>@endif
        </div>

        <x-field name="purchase_date" label="Kaufdatum" type="date" :value="$license->purchase_date?->format('Y-m-d')" />
        <x-field name="purchase_price" label="Preis (pro Zahlung)" type="number" :value="$license->purchase_price" step="0.01" min="0" />
        <x-field name="currency" label="Währung" :value="$license->currency ?? 'EUR'" maxlength="3" required />
        <div>
            <label for="billing_interval" class="mb-1.5 block text-sm font-medium">Zahlungsintervall *</label>
            <select id="billing_interval" name="billing_interval" class="field">@foreach($intervals as $i)<option value="{{ $i->value }}" @selected(old('billing_interval', $license->billing_interval?->value) === $i->value)>{{ $i->label() }}</option>@endforeach</select>
            @error('billing_interval')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>
        <x-field name="expires_at" label="Ablaufdatum" type="date" :value="$license->expires_at?->format('Y-m-d')" />
        <x-field name="renewal_date" label="Verlängerungsdatum" type="date" :value="$license->renewal_date?->format('Y-m-d')" />

        <div>
            <label for="category" class="mb-1.5 block text-sm font-medium">Kategorie</label>
            <input id="category" name="category" list="cats" value="{{ old('category', $license->category?->name) }}" class="field" maxlength="60">
            <datalist id="cats">@foreach($categoryNames as $c)<option value="{{ $c }}">@endforeach</datalist>
            @error('category')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="tags" class="mb-1.5 block text-sm font-medium">Tags <span class="font-normal text-ink3">(Komma-getrennt)</span></label>
            <input id="tags" name="tags" value="{{ old('tags', $license->tags->pluck('name')->implode(', ')) }}" class="field" maxlength="300">
            @error('tags')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label for="notes" class="mb-1.5 block text-sm font-medium">Notizen</label>
            <textarea id="notes" name="notes" rows="4" maxlength="5000" class="field">{{ old('notes', $license->notes) }}</textarea>
            @error('notes')<p class="mt-1.5 text-sm text-er">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="mt-6 flex justify-end gap-2">
        <a href="{{ $edit ? route('licenses.show', $license) : route('licenses.index') }}" class="btn btn-ghost">Abbrechen</a>
        <button class="btn">{{ $edit ? 'Speichern' : 'Lizenz anlegen' }}</button>
    </div>
</form>
@endsection
