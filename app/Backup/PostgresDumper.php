<?php

namespace App\Backup;

use RuntimeException;
use Symfony\Component\Process\Process;

/** pg_dump/pg_restore gegen die konfigurierte Verbindung (internes oder externes PostgreSQL). */
class PostgresDumper implements DatabaseDumper
{
    public function dump(string $file): void
    {
        $this->run(['pg_dump', '--format=custom', '--no-owner', '--no-privileges', '--file='.$file, $this->db()]);
    }

    /**
     * Restore als EINE Transaktion: pg_restore erzeugt SQL, versionsspezifische SET-Zeilen,
     * die ältere Server nicht kennen, werden entfernt, psql spielt alles mit ON_ERROR_STOP
     * in einer Transaktion ein. Schlägt etwas fehl, bleibt die Datenbank unverändert.
     */
    public function restore(string $file): void
    {
        $sql = $file.'.sql';
        $clean = $file.'.clean.sql';

        try {
            $this->run(['pg_restore', '--clean', '--if-exists', '--no-owner', '--no-privileges', '--file='.$sql, $file]);
            $this->filterVersionSpecificSettings($sql, $clean);
            $this->run(['psql', '--no-psqlrc', '--quiet', '--single-transaction', '--set=ON_ERROR_STOP=1', '--dbname='.$this->db(), '--file='.$clean]);
        } finally {
            @unlink($sql);
            @unlink($clean);
        }
    }

    private function filterVersionSpecificSettings(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = fopen($to, 'wb');
        while (($line = fgets($in)) !== false) {
            if (! preg_match('/^SET (transaction_timeout|idle_session_timeout) = /', $line)) {
                fwrite($out, $line);
            }
        }
        fclose($in);
        fclose($out);
    }

    public function toolVersion(): string
    {
        $p = new Process(['pg_dump', '--version']);
        $p->run();

        return trim($p->getOutput()) ?: 'unbekannt';
    }

    private function db(): string
    {
        return (string) config('database.connections.pgsql.database');
    }

    /** Zugangsdaten nur über die Prozessumgebung – nie in Argumenten oder Logs. */
    private function run(array $cmd): void
    {
        $c = config('database.connections.pgsql');
        $p = new Process(array_merge([$cmd[0], '--host='.$c['host'], '--port='.$c['port'], '--username='.$c['username']], array_slice($cmd, 1)), null, [
            'PGPASSWORD' => (string) $c['password'],
            'PGSSLMODE' => (string) ($c['sslmode'] ?? 'prefer'),
        ]);
        $p->setTimeout(3600);
        $p->run();

        if (! $p->isSuccessful()) {
            throw new RuntimeException($cmd[0].' fehlgeschlagen: '.trim(str_replace((string) $c['password'], '***', $p->getErrorOutput())));
        }
    }
}
