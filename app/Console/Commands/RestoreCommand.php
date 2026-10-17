<?php

namespace App\Console\Commands;

use App\Backup\BackupService;
use Illuminate\Console\Command;

class RestoreCommand extends Command
{
    protected $signature = 'licensevault:restore {file : Dateiname in backups/ oder voller Pfad}
        {--dry-run : Nur prüfen und Vorschau anzeigen}
        {--force : Ohne Rückfrage ausführen}
        {--ignore-key-mismatch : Auch bei abweichendem APP_KEY einspielen (verschlüsselte Daten bleiben dann unlesbar)}';

    protected $description = 'Prüft eine Sicherung und spielt sie nach Bestätigung ein (mit automatischer Sicherheitskopie).';

    public function handle(BackupService $backups): int
    {
        $arg = $this->argument('file');
        $file = is_file($arg) ? $arg : $backups->dir().'/'.basename($arg);
        if (! is_file($file)) {
            $this->error('Sicherung nicht gefunden: '.$arg);

            return self::FAILURE;
        }

        $this->line('Prüfe Sicherung (Entschlüsselung, Prüfsummen)…');
        try {
            $opened = $backups->open($file);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $m = $opened['manifest'];
            $this->table(['Feld', 'Wert'], [
                ['Erstellt', $m['created_at']],
                ['App-Version', $m['app_version']],
                ['Datenbank-Werkzeug', $m['db_tool']],
                ['Dateien (Uploads)', count($m['files'] ?? [])],
                ...collect($m['counts'])->map(fn ($n, $t) => ["Datensätze {$t}", $n])->values()->all(),
            ]);

            $problems = $backups->compatibility($m);
            foreach ($problems as $p) {
                $this->error($p);
            }
            if ($problems) {
                return self::FAILURE;
            }

            $keyOk = $backups->keyMatches($m);
            if (! $keyOk) {
                $this->warn('APP_KEY weicht von dem der Sicherung ab: verschlüsselte Lizenzschlüssel/Codes wären nach dem Restore NICHT lesbar.');
                if (! $this->option('ignore-key-mismatch')) {
                    $this->error('Abbruch. Den ursprünglichen APP_KEY eintragen (oder --ignore-key-mismatch verwenden).');

                    return self::FAILURE;
                }
            }

            if ($this->option('dry-run')) {
                $this->info('Prüfung erfolgreich (Dry-Run, nichts verändert).');

                return self::SUCCESS;
            }

            $this->warn('ACHTUNG: Datenbank und Uploads werden vollständig durch den Stand der Sicherung ersetzt.');
            if (! $this->option('force') && ! $this->confirm('Jetzt wiederherstellen? Vorher wird automatisch eine Sicherheitskopie erstellt.', false)) {
                $this->line('Abgebrochen.');

                return self::SUCCESS;
            }

            try {
                $safety = $backups->restore($opened['dir']);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info('Wiederherstellung abgeschlossen. Sicherheitskopie des vorherigen Stands: '.basename($safety));
        } finally {
            $backups->cleanup($opened['dir']);
        }

        return self::SUCCESS;
    }
}
