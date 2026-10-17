<?php

namespace App\Console\Commands;

use App\Notifications\Notifier;
use App\Notifications\PushMessage;
use Illuminate\Console\Command;

class NotifyTest extends Command
{
    protected $signature = 'licensevault:notify-test';

    protected $description = 'Sendet eine Testnachricht über alle eingerichteten Kanäle (Web-Push, Gotify).';

    public function handle(Notifier $notifier): int
    {
        if (! $notifier->anyConfigured()) {
            $this->error('Kein Kanal eingerichtet (VAPID_* bzw. GOTIFY_URL/GOTIFY_TOKEN in der .env).');

            return self::FAILURE;
        }
        foreach ($notifier->send(new PushMessage('LicenseVault', 'Testnachricht – Benachrichtigungen funktionieren.', '/erinnerungen', 'test')) as $name => $n) {
            $this->line("{$name}: {$n} zugestellt");
        }

        return self::SUCCESS;
    }
}
