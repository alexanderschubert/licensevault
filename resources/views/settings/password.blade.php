@extends('layouts.app')
@section('title', 'Einstellungen')
@section('content')
<h1 class="text-2xl font-bold tracking-tight">Einstellungen</h1>
<div class="mt-4"><x-settings-tabs /></div>
<form method="POST" action="{{ route('settings.password.update') }}" class="card mt-6 max-w-lg space-y-4 p-6">
    @csrf @method('PUT')
    @if (session('status'))<div class="pill pill-ok" role="status"><x-icon name="check" class="size-4" />{{ session('status') }}</div>@endif
    @foreach ([['current_password','Aktuelles Passwort','current-password'],['password','Neues Passwort (mind. 12 Zeichen)','new-password'],['password_confirmation','Neues Passwort wiederholen','new-password']] as [$n,$l,$ac])
        <div>
            <label for="{{ $n }}" class="mb-1.5 block text-sm font-medium">{{ $l }}</label>
            <input id="{{ $n }}" name="{{ $n }}" type="password" required autocomplete="{{ $ac }}" class="field">
            @error($n)<p class="mt-1.5 text-sm text-er" role="alert">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <button class="btn">Passwort speichern</button>
</form>
@endsection
