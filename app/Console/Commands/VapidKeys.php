<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class VapidKeys extends Command
{
    protected $signature = 'licensevault:vapid-keys';

    protected $description = 'Erzeugt ein VAPID-Schlüsselpaar für Web-Push (wird nur angezeigt, nicht gespeichert).';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('Trage diese Zeilen in deine .env ein (privaten Schlüssel geheim halten, nicht committen):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:du@example.com');
        $this->newLine();
        $this->warn('Wechselst du die Schlüssel später, müssen alle Geräte die Benachrichtigungen neu aktivieren.');

        return self::SUCCESS;
    }
}
