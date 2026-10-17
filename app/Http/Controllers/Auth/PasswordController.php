<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('settings.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->mixedCase()->numbers()],
        ], [
            'current_password.current_password' => 'Das aktuelle Passwort ist nicht korrekt.',
            'password.confirmed' => 'Die Passwort-Bestätigung stimmt nicht überein.',
            'password.different' => 'Das neue Passwort muss sich vom alten unterscheiden.',
            'password.min' => 'Das Passwort muss mindestens 12 Zeichen lang sein.',
        ]);

        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return back()->with('status', 'Passwort wurde geändert.');
    }
}
