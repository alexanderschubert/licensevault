<?php

namespace App\Console\Commands;

use App\Notifications\Notifier;
use App\Services\ReminderGenerator;
use Illuminate\Console\Command;

class GenerateReminders extends Command
{
    protected $signature = 'licensevault:generate-reminders';

    protected $description = 'Erzeugt Erinnerungen für ablaufende Lizenzen und Verlängerungen.';

    public function handle(ReminderGenerator $generator, Notifier $notifier): int
    {
        $this->info($generator->run().' neue Erinnerung(en).');
        if ($sent = $notifier->notifyReminders($generator->created())) {
            $this->line($sent.' Benachrichtigung(en) versendet.');
        }

        return self::SUCCESS;
    }
}
