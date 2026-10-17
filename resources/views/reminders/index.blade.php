@extends('layouts.app')
@section('title', 'Erinnerungen')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-2xl font-bold tracking-tight">Erinnerungen</h1>
        <p class="mt-1 text-ink2">Hinweise {{ implode(', ', $days) }} Tage vor Ablauf oder Verlängerung · tägliche Prüfung um 06:00</p></div>
    <div class="flex gap-2">
        <form method="POST" action="{{ route('reminders.check') }}">@csrf<button class="btn btn-ghost">Jetzt prüfen</button></form>
        @if($open->isNotEmpty())<form method="POST" action="{{ route('reminders.dismiss-all') }}">@csrf<button class="btn btn-ghost">Alle erledigt</button></form>@endif
    </div>
</div>
<div class="mt-5"><x-flash /></div>

<div class="card">
    @forelse($open as $r)
        <div class="flex items-center gap-4 border-t border-line px-5 py-4 first:border-0">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-acs text-ac"><x-icon name="bell" /></span>
            <div class="min-w-0 flex-1">
                <a href="{{ route('licenses.show', $r->license) }}" class="block truncate font-semibold hover:text-ac">{{ $r->license->name }}</a>
                <span class="text-sm text-ink2">{{ $r->message() }} · {{ $r->due_on->format('d.m.Y') }}</span>
            </div>
            <span class="pill {{ $r->severity() }}">{{ $r->kind === 'renewal' ? 'Verlängerung' : 'Ablauf' }}</span>
            <form method="POST" action="{{ route('reminders.dismiss', $r) }}">@csrf<button class="btn btn-ghost !py-2"><x-icon name="check" class="size-4" /> Erledigt</button></form>
        </div>
    @empty
        <div class="grid place-items-center p-16 text-center text-ink2">Keine offenen Erinnerungen. 🎉</div>
    @endforelse
</div>

@if($done->isNotEmpty())
<h2 class="mb-2 mt-8 text-sm font-semibold text-ink2">Zuletzt erledigt</h2>
<div class="card divide-y divide-line text-sm">
    @foreach($done as $r)<div class="flex items-center justify-between px-5 py-3 text-ink2"><span>{{ $r->license?->name }} · {{ $r->due_on->format('d.m.Y') }}</span><span>{{ $r->dismissed_at->format('d.m.Y') }}</span></div>@endforeach
</div>
@endif
@endsection
