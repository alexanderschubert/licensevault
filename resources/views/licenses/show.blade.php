@extends('layouts.app')
@section('title', $license->name)
@section('content')
@php $st = $license->expiryStatus(); @endphp
<a href="{{ route('licenses.index') }}" class="text-sm text-ink2 hover:text-ac">← Alle Lizenzen</a>
<div class="mt-3 flex flex-wrap items-start justify-between gap-4">
    <div class="flex items-start gap-4">
        <x-license-icon :license="$license" size="size-16" />
        <div>
        <h1 class="text-2xl font-bold tracking-tight">{{ $license->name }}</h1>
        <p class="mt-1 text-ink2">{{ $license->vendor }}{{ $license->vendor && $license->product_version ? ' · ' : '' }}{{ $license->product_version }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach($license->platformEnums() as $pl)<span class="pill pill-ac">{{ $pl->label() }}</span>@endforeach
            <span class="pill {{ $license->license_type->pill() }}">{{ $license->license_type->label() }}</span>
            <span class="pill {{ $st['class'] }}">{{ $license->expires_at ? 'Ablauf: '.$st['label'] : 'unbegrenzt' }}</span>
            @if($license->category)<span class="pill">{{ $license->category->name }}</span>@endif
            @foreach($license->tags as $t)<span class="pill">#{{ $t->name }}</span>@endforeach
        </div>
        </div>
    </div>
    <div class="flex gap-2" x-data="{ confirm: false }">
        <a href="{{ route('licenses.edit', $license) }}" class="btn btn-ghost"><x-icon name="edit" /> Bearbeiten</a>
        <button type="button" class="btn btn-ghost !text-er" @click="confirm = true"><x-icon name="trash" /> Löschen</button>
        <div x-show="confirm" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" @keydown.escape.window="confirm = false" role="dialog" aria-modal="true">
            <div class="card w-full max-w-sm p-6" @click.outside="confirm = false">
                <h2 class="text-lg font-bold">Lizenz löschen?</h2>
                <p class="mt-2 text-sm text-ink2">„{{ $license->name }}“ wird unwiderruflich gelöscht, inklusive des gespeicherten Schlüssels.</p>
                <form method="POST" action="{{ route('licenses.destroy', $license) }}" class="mt-5 flex justify-end gap-2">@csrf @method('DELETE')
                    <button type="button" class="btn btn-ghost" @click="confirm = false">Abbrechen</button>
                    <button class="btn btn-danger">Endgültig löschen</button>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="mt-5"><x-flash /></div>

<div class="mt-2 grid gap-5 lg:grid-cols-[1fr_380px]">
    <div class="card p-6">
        <dl class="grid grid-cols-[150px_1fr] gap-x-4 gap-y-3 text-sm">
            <dt class="text-ink3">Kaufdatum</dt><dd>{{ $license->purchase_date?->format('d.m.Y') ?? '—' }}</dd>
            <dt class="text-ink3">Preis</dt><dd>{{ $license->priceLabel() }}</dd>
            @if($license->billing_interval === \App\Enums\BillingInterval::Monthly)<dt class="text-ink3">≙ pro Jahr</dt><dd>{{ number_format($license->yearlyCost(), 2, ',', '.') }} {{ $license->currency }}</dd>@endif
            <dt class="text-ink3">Ablaufdatum</dt><dd>{{ $license->expires_at?->format('d.m.Y') ?? 'unbegrenzt' }}</dd>
            <dt class="text-ink3">Verlängerung</dt><dd>{{ $license->renewal_date?->format('d.m.Y') ?? '—' }}</dd>
            <dt class="text-ink3">Gerätelimit</dt><dd>{{ $license->device_limit ?? '—' }}</dd>
            <dt class="text-ink3">Installiert auf</dt>
            <dd>@if($license->devices->isEmpty())—@else<div class="flex flex-wrap gap-1.5">@foreach($license->devices as $dv)<span class="pill">{{ $dv->name }}</span>@endforeach</div>
                <span class="mt-1 block text-xs text-ink3">{{ $license->devices->count() }}{{ $license->device_limit ? ' von '.$license->device_limit : '' }} Gerät(e) genutzt{{ $license->device_limit && $license->devices->count() > $license->device_limit ? ' – Limit überschritten!' : '' }}</span>@endif</dd>
            @if($license->customer_number)<dt class="text-ink3">Kundennummer</dt><dd class="break-all">{{ $license->customer_number }}</dd>@endif
            @if($license->support_contact)<dt class="text-ink3">Support</dt><dd class="break-all">@if(preg_match('#^https?://#i', $license->support_contact))<a href="{{ $license->support_contact }}" target="_blank" rel="noopener noreferrer" class="text-ac hover:underline">{{ $license->support_contact }}</a>@else{{ $license->support_contact }}@endif</dd>@endif
        </dl>
        @if($license->notes)
            <div class="mt-5 border-t border-line pt-4"><div class="mb-1 text-xs font-semibold uppercase tracking-wider text-ink3">Notizen</div><p class="whitespace-pre-line text-sm">{{ $license->notes }}</p></div>
        @endif
    </div>

    <div class="space-y-5">
    <div class="card h-fit p-6" x-data="secretBox('{{ route('licenses.reveal', $license) }}')">
        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink3"><x-icon name="lock" class="size-4" /> Lizenzschlüssel · verschlüsselt</div>
        @if($license->hasSecret())
            <code class="block break-all rounded-xl bg-panel2 px-3.5 py-3 font-mono text-sm" x-text="value ?? '••••••••••••••••'"></code>
            <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-er"></p>
            <div class="mt-3 flex gap-2">
                <button type="button" class="btn btn-ghost !py-2" @click="value ? hide() : show()"><x-icon name="eye" /><span x-text="value ? 'Verbergen' : 'Anzeigen (30 s)'"></span></button>
                <button type="button" class="btn btn-ghost !py-2" x-show="value" x-cloak @click="copy()"><x-icon name="copy" /><span x-text="copied ? 'Kopiert' : 'Kopieren'"></span></button>
            </div>
        @else
            <p class="text-sm text-ink2">Kein Schlüssel hinterlegt.</p>
        @endif
    </div>

    @if($license->hasAccount())
    <div class="card h-fit p-6" x-data="secretBox('{{ route('licenses.reveal-account', $license) }}')">
        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink3"><x-icon name="lock" class="size-4" /> Konto-Zugang</div>
        @if($license->account_login)
            <div class="text-xs text-ink3">Benutzername / E-Mail</div>
            <div class="mb-3 break-all font-medium">{{ $license->account_login }}</div>
        @endif
        @if($license->account_url)
            <a href="{{ $license->account_url }}" target="_blank" rel="noopener noreferrer" class="mb-3 block break-all text-sm text-ac hover:underline">{{ $license->account_url }}</a>
        @endif
        @if($license->hasAccountPassword())
            <div class="text-xs text-ink3">Passwort · verschlüsselt</div>
            <code class="mt-1 block break-all rounded-xl bg-panel2 px-3.5 py-3 font-mono text-sm" x-text="value ?? '••••••••••••'"></code>
            <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-er"></p>
            <div class="mt-3 flex gap-2">
                <button type="button" class="btn btn-ghost !py-2" @click="value ? hide() : show()"><x-icon name="eye" /><span x-text="value ? 'Verbergen' : 'Anzeigen (30 s)'"></span></button>
                <button type="button" class="btn btn-ghost !py-2" x-show="value" x-cloak @click="copy()"><x-icon name="copy" /><span x-text="copied ? 'Kopiert' : 'Kopieren'"></span></button>
            </div>
        @endif
    </div>
    @endif

    @if($license->hasRecoveryCodes())
    <div class="card h-fit p-6" x-data="secretBox('{{ route('licenses.reveal-recovery', $license) }}')">
        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink3"><x-icon name="lock" class="size-4" /> 2FA-/Wiederherstellungscodes · verschlüsselt</div>
        <pre class="whitespace-pre-wrap break-all rounded-xl bg-panel2 px-3.5 py-3 font-mono text-sm" x-text="value ?? '••••••••••••'"></pre>
        <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-er"></p>
        <div class="mt-3 flex gap-2">
            <button type="button" class="btn btn-ghost !py-2" @click="value ? hide() : show()"><x-icon name="eye" /><span x-text="value ? 'Verbergen' : 'Anzeigen (30 s)'"></span></button>
            <button type="button" class="btn btn-ghost !py-2" x-show="value" x-cloak @click="copy()"><x-icon name="copy" /><span x-text="copied ? 'Kopiert' : 'Kopieren'"></span></button>
        </div>
    </div>
    @endif
    </div>
</div>
<div class="mt-5"><x-attachments :model="$license" key="license" /></div>

@endsection
