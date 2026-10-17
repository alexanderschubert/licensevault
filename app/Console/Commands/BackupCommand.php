<?php

namespace App\Console\Commands;

use App\Backup\BackupService;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'licensevault:backup {--keep= : Anzahl der aufzubewahrenden Sicherungen (Standard BACKUP_KEEP)}';

    protected $description = 'Erstellt eine verschlüsselte Sicherung (Datenbank + Uploads).';

    public function handle(BackupService $backups): int
    {
        try {
            $path = $backups->create();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sicherung erstellt: '.basename($path).' ('.number_format(filesize($path) / 1024, 0, ',', '.').' KB)');
        $removed = $backups->prune((int) ($this->option('keep') ?: config('licensevault.backup.keep')));
        if ($removed) {
            $this->line("{$removed} alte Sicherung(en) entfernt.");
        }
        $this->warn('Erinnerung: APP_KEY und BACKUP_PASSPHRASE getrennt von der Sicherung aufbewahren – ohne sie ist ein Restore nicht möglich.');

        return self::SUCCESS;
    }
}
