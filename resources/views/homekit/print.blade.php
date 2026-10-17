@extends('layouts.base')
@section('title', 'Druckansicht')
@section('body')
<div class="mx-auto max-w-5xl p-6">
    <div class="mb-6 flex items-center justify-between print:hidden">
        <div><h1 class="text-2xl font-bold">Druckansicht · HomeKit &amp; Matter</h1><p class="text-sm text-ink2">Enthält Kopplungs-Codes – nach dem Drucken sicher aufbewahren.</p></div>
        <div class="flex gap-2"><a href="{{ route('homekit.print.pdf', $filters) }}" class="btn btn-ghost">Als PDF</a><button class="btn" onclick="lvPrint('{{ route('homekit.print.pdf', $filters) }}')">Drucken</button></div>
    </div>
    @if($devices->isEmpty())<p class="text-ink2">Keine Geräte mit QR-Code für diesen Filter.</p>@endif
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        @foreach($devices as $d)
            <div class="break-inside-avoid rounded-xl border border-line bg-white p-3 text-center text-[#14152b]">
                <img src="{{ route('homekit.qr', $d) }}" alt="QR-Code {{ $d->name }}" class="mx-auto aspect-square w-full max-w-[200px]">
                <div class="mt-2 text-sm font-semibold">{{ $d->name }}</div>
                <div class="text-xs text-gray-500">{{ $d->room }}{{ $d->room && $d->model ? ' · ' : '' }}{{ $d->model }}</div>
            </div>
        @endforeach
    </div>
</div>
@endsection
