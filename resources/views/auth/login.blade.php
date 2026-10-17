@extends('layouts.guest')
@section('title', 'Anmelden')
@section('content')
<form method="POST" action="{{ route('login.store') }}" class="space-y-4">
    @csrf
    <div>
        <label for="email" class="mb-1.5 block text-sm font-medium">E-Mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field">
        @error('email')<p class="mt-1.5 text-sm text-er" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="mb-1.5 block text-sm font-medium">Passwort</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" class="field">
    </div>
    <label class="flex items-center gap-2 text-sm text-ink2"><input type="checkbox" name="remember" class="size-4 rounded accent-[var(--ac)]"> Angemeldet bleiben</label>
    <button class="btn w-full"><x-icon name="lock" /> Anmelden</button>
</form>
<p class="mt-6 text-center text-xs text-ink3">Einzelnutzer-Instanz · keine Registrierung</p>
@endsection
