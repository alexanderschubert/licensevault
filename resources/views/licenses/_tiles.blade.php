@if($licenses->isEmpty())
    <div class="card grid place-items-center p-16 text-center text-ink2">{{ $total ? 'Keine Lizenzen für diesen Filter.' : 'Noch keine Lizenzen. Lege deine erste an.' }}</div>
@else
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
    @foreach($licenses as $l)
        @php $st = $l->expiryStatus(); @endphp
        <div class="card group relative flex flex-col gap-3 p-5 transition hover:border-ac">
            <div class="flex items-start gap-3">
                <x-license-icon :license="$l" size="size-12" />
                <div class="min-w-0 flex-1">
                    <a href="{{ route('licenses.show', $l) }}" class="block truncate font-semibold after:absolute after:inset-0 after:content-[''] hover:text-ac">{{ $l->name }}</a>
                    <span class="block truncate text-xs text-ink3">{{ $l->vendor }}{{ $l->vendor && $l->product_version ? ' · ' : '' }}{{ $l->product_version }}</span>
                </div>
                <a href="{{ route('licenses.edit', $l) }}" class="relative z-10 rounded-lg border border-line p-1.5 text-ink3 hover:bg-panel2 hover:text-ac" aria-label="{{ $l->name }} bearbeiten" title="Bearbeiten"><x-icon name="edit" class="size-4" /></a>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <span class="pill {{ $l->license_type->pill() }}">{{ $l->license_type->label() }}</span>
                <span class="pill {{ $st['class'] }}">{{ $l->expires_at ? $st['label'] : 'unbegrenzt' }}</span>
                @foreach($l->platformEnums() as $pl)<span class="pill">{{ $pl->label() }}</span>@endforeach
            </div>
            @if($l->keys->isNotEmpty())
            <div class="relative z-10 flex flex-wrap gap-1.5">
                @foreach($l->keys as $k)
                    <div x-data="secretBox('{{ route('licenses.reveal', [$l, $k]) }}')" class="min-w-0 max-w-full">
                        <button type="button" class="inline-flex max-w-full items-center gap-1.5 rounded-lg bg-panel2 px-2.5 py-1 text-xs text-ink2 hover:text-ac" @click="value ? copy() : show()" :title="value ? 'Zum Kopieren klicken' : 'Zum Anzeigen klicken (30 s)'">
                            <x-icon name="lock" class="size-3.5 shrink-0" />
                            <span class="truncate font-mono" x-text="value ? (copied ? 'Kopiert' : value) : @js($k->label ?: 'Schlüssel '.($loop->index + 1))">{{ $k->label ?: 'Schlüssel '.($loop->index + 1) }}</span>
                        </button>
                        <p x-show="error" x-cloak x-text="error" class="mt-1 text-xs text-er"></p>
                    </div>
                @endforeach
            </div>
            @endif
            <div class="mt-auto flex items-end justify-between gap-2 border-t border-line pt-3 text-sm">
                <div class="min-w-0 text-ink2">
                    @if($l->devices->isNotEmpty())<div class="truncate text-xs" title="{{ $l->devices->pluck('name')->implode(', ') }}">{{ $l->devices->count() }}{{ $l->device_limit ? ' / '.$l->device_limit : '' }} Gerät(e) · {{ $l->devices->pluck('name')->implode(', ') }}</div>
                    @elseif($l->device_limit)<div class="text-xs text-ink3">bis {{ $l->device_limit }} Geräte</div>@endif
                    <div class="mt-1 flex gap-1.5 text-ink3">@if($l->hasAccount())<span class="inline-flex items-center gap-1 text-xs"><x-icon name="key" class="size-3.5" />Konto</span>@endif</div>
                </div>
                <div class="shrink-0 font-semibold">{{ $l->priceLabel() }}</div>
            </div>
        </div>
    @endforeach
</div>
@endif
