<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Notifications\Notifier;
use App\Services\ReminderGenerator;
use Illuminate\Http\RedirectResponse;

class ReminderController extends Controller
{
    public function index()
    {
        return view('reminders.index', [
            'open' => Reminder::open()->with('license')->get()->sortBy(fn ($r) => $r->due_on)->values(),
            'done' => Reminder::whereNotNull('dismissed_at')->with('license')->latest('dismissed_at')->limit(10)->get(),
            'days' => config('licensevault.reminder_days'),
        ]);
    }

    public function check(ReminderGenerator $generator, Notifier $notifier): RedirectResponse
    {
        $n = $generator->run();
        $notifier->notifyReminders($generator->created());

        return back()->with('status', $n ? "{$n} neue Erinnerung(en) erzeugt." : 'Keine neuen Erinnerungen.');
    }

    public function dismiss(Reminder $reminder): RedirectResponse
    {
        $reminder->update(['dismissed_at' => now()]);

        return back();
    }

    public function dismissAll(): RedirectResponse
    {
        Reminder::open()->update(['dismissed_at' => now()]);

        return back()->with('status', 'Alle Erinnerungen als erledigt markiert.');
    }
}
