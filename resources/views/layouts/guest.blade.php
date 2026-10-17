@extends('layouts.base')
@section('body')
<div class="hero-bg grid min-h-screen place-items-center px-4 py-10">
    <span class="blob"></span>
    <div class="absolute right-5 top-5"><x-theme-toggle /></div>
    <div class="glass w-full max-w-md rounded-3xl p-8 sm:p-10">
        <div class="mb-8 flex items-center gap-3">
            <x-logo size="size-11" glyph="size-6" radius="rounded-2xl" />
            <div><div class="text-xl font-bold tracking-tight">LicenseVault</div><div class="text-sm text-ink2">Deine Lizenzen &amp; Codes, privat gehostet.</div></div>
        </div>
        @yield('content')
    </div>
</div>
@endsection
