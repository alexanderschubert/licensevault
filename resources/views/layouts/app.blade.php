@extends('layouts.base')
@section('body')
<div class="flex min-h-screen" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <div x-show="nav" x-cloak x-transition.opacity @click="nav = false" class="fixed inset-0 z-30 bg-black/50 lg:hidden print:hidden" aria-hidden="true"></div>
    <aside x-bind:class="nav && 'max-lg:!translate-x-0'" class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 max-lg:-translate-x-full flex-col gap-1 overflow-y-auto border-r border-line bg-panel px-3.5 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-60 lg:translate-x-0 print:!hidden">
        <div class="flex items-center gap-2.5 px-2.5 pb-5 text-lg font-bold tracking-tight">
            <x-logo />
            LicenseVault
        </div>
        <div class="px-3 pb-1.5 pt-3 text-[11px] font-semibold uppercase tracking-widest text-ink3">Übersicht</div>
        <x-nav-item route="dashboard" icon="grid">Dashboard</x-nav-item>
        <div class="px-3 pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-widest text-ink3">Verwalten</div>
        <x-nav-item route="licenses.index" match="licenses.*" icon="key">Lizenzen</x-nav-item>
        <x-nav-item route="homekit.index" match="homekit.*" icon="qr">HomeKit &amp; Matter</x-nav-item>
        <x-nav-item route="documents.index" match="documents.*" icon="doc">Dokumente</x-nav-item>
        <x-nav-item route="reminders.index" match="reminders.*" icon="bell" :count="$openReminders">Erinnerungen</x-nav-item>
        <div class="px-3 pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-widest text-ink3">System</div>
        <x-nav-item route="backup.index" match="backup.*" icon="save">Backup &amp; Export</x-nav-item>
        <x-nav-item route="settings.password" match="settings.*" icon="gear">Einstellungen</x-nav-item>
        <div class="flex-1"></div>
        <div class="flex items-center gap-2.5 border-t border-line px-2 pt-4">
            <span class="grid size-9 place-items-center rounded-full bg-gradient-to-br from-amber-400 to-rose-500 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span>
            <div class="min-w-0 flex-1 text-sm"><div class="truncate font-semibold">{{ auth()->user()->name }}</div><div class="text-xs text-ink3">Administrator</div></div>
            <div class="flex gap-2 sm:hidden"><x-theme-toggle class="!size-9" />
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="grid size-9 place-items-center rounded-xl border border-line bg-panel text-ink2" aria-label="Abmelden"><x-icon name="logout" /></button></form></div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-0">
        <header class="sticky top-0 z-20 flex h-[68px] items-center gap-3 border-b border-line bg-panel px-4 sm:px-5 lg:px-8 print:hidden">
            <button type="button" @click="nav = true" class="grid size-10 shrink-0 place-items-center rounded-xl border border-line bg-panel text-ink2 lg:hidden" aria-label="Menü öffnen"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <form method="GET" action="{{ route('licenses.index') }}" class="flex min-w-0 flex-1 items-center gap-2.5 rounded-xl bg-panel2 px-3.5 py-2.5 text-sm text-ink3" role="search"><x-icon name="search" /><input name="q" type="search" placeholder="Lizenzen durchsuchen…" aria-label="Lizenzen durchsuchen" class="w-full bg-transparent text-ink outline-none placeholder:text-ink3"></form>
            <div class="hidden flex-1 lg:block"></div>
            <a href="{{ route('reminders.index') }}" class="relative grid size-10 place-items-center rounded-xl border border-line bg-panel text-ink2 hover:bg-panel2" aria-label="Erinnerungen ({{ $openReminders }} offen)"><x-icon name="bell" />@if($openReminders)<span class="absolute right-2 top-2 size-2.5 rounded-full border-2 border-panel bg-er"></span>@endif</a>
            <x-theme-toggle class="hidden sm:grid" />
            <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">@csrf
                <button class="grid size-10 place-items-center rounded-xl border border-line bg-panel text-ink2 hover:bg-panel2" aria-label="Abmelden" title="Abmelden"><x-icon name="logout" /></button>
            </form>
        </header>
        <main class="min-w-0 flex-1 px-4 py-6 sm:px-5 sm:py-7 lg:px-8">
            @yield('content')
        </main>
    </div>
</div>
@endsection
