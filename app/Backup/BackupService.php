<?php

namespace App\Backup;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Process\Process;

/**
 * Sicherung = verschlüsseltes tar.gz aus manifest.json, db.dump und uploads/.
 * Der APP_KEY ist bewusst NICHT enthalten (getrennt aufbewahren!); das Manifest
 * enthält nur einen Fingerabdruck, um beim Restore einen falschen Schlüssel zu erkennen.
 */
class BackupService
{
    public const FORMAT = 1;

    public const FILE_PATTERN = '/^licensevault-\d{8}-\d{6}(-pre-restore)?\.lvbak$/';

    public function __construct(private DatabaseDumper $dumper) {}

    public function dir(): string
    {
        $d = storage_path('app/private/backups');
        File::ensureDirectoryExists($d, 0700);

        return $d;
    }

    public function uploadsDir(): string
    {
        $d = storage_path('app/private/uploads');
        File::ensureDirectoryExists($d, 0700);

        return $d;
    }

    private function passphrase(): string
    {
        return (string) config('licensevault.backup.passphrase');
    }

    public function keyFingerprint(): string
    {
        return substr(hash('sha256', 'lv-key|'.config('app.key')), 0, 12);
    }

    /** @return string Pfad der erzeugten Sicherung */
    public function create(bool $preRestore = false): string
    {
        $pass = $this->passphrase();
        $work = $this->tempDir('lvb-');

        try {
            File::ensureDirectoryExists($work.'/pack');
            $this->dumper->dump($work.'/pack/db.dump');
            $files = $this->copyUploads($work.'/pack/uploads');

            $manifest = [
                'format' => self::FORMAT,
                'app' => 'licensevault',
                'app_version' => config('licensevault.version'),
                'created_at' => now()->toIso8601String(),
                'key_fingerprint' => $this->keyFingerprint(),
                'db_tool' => $this->dumper->toolVersion(),
                'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
                'counts' => $this->counts(),
                'db_sha256' => hash_file('sha256', $work.'/pack/db.dump'),
                'files' => $files,
            ];
            file_put_contents($work.'/pack/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->exec(['tar', '-czf', $work.'/bundle.tar.gz', '-C', $work.'/pack', '.']);

            $name = 'licensevault-'.now()->format('Ymd-His').($preRestore ? '-pre-restore' : '').'.lvbak';
            $target = $this->dir().'/'.$name;
            BackupCrypto::encryptFile($work.'/bundle.tar.gz', $target.'.part', $pass);
            rename($target.'.part', $target);
            chmod($target, 0600);

            return $target;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Entschlüsselt und prüft eine Sicherung vollständig (ohne etwas zu verändern).
     *
     * @return array{manifest: array, dir: string} dir = entpackter Inhalt (Aufrufer räumt auf via cleanup())
     */
    public function open(string $file): array
    {
        $work = $this->tempDir('lvr-');

        try {
            BackupCrypto::decryptFile($file, $work.'/bundle.tar.gz', $this->passphrase());

            $list = $this->exec(['tar', '-tzf', $work.'/bundle.tar.gz']);
            foreach (preg_split('/\R/', trim($list)) as $entry) {
                if ($entry === '') {
                    continue;
                }
                if (str_starts_with($entry, '/') || preg_match('#(^|/)\.\.(/|$)#', $entry)) {
                    throw new RuntimeException('Sicherung enthält unzulässige Pfade.');
                }
            }

            File::ensureDirectoryExists($work.'/pack');
            $this->exec(['tar', '-xzf', $work.'/bundle.tar.gz', '-C', $work.'/pack']);

            $manifest = json_decode((string) @file_get_contents($work.'/pack/manifest.json'), true);
            if (! is_array($manifest) || ($manifest['app'] ?? null) !== 'licensevault') {
                throw new RuntimeException('Manifest fehlt oder ist ungültig.');
            }
            if (($manifest['format'] ?? 0) > self::FORMAT) {
                throw new RuntimeException('Sicherung stammt aus einer neueren LicenseVault-Version.');
            }
            if (! hash_equals((string) $manifest['db_sha256'], (string) @hash_file('sha256', $work.'/pack/db.dump'))) {
                throw new RuntimeException('Prüfsumme der Datenbank stimmt nicht.');
            }
            foreach ($manifest['files'] ?? [] as $rel => $hash) {
                if (! hash_equals($hash, (string) @hash_file('sha256', $work.'/pack/uploads/'.$rel))) {
                    throw new RuntimeException("Prüfsumme der Datei {$rel} stimmt nicht.");
                }
            }

            return ['manifest' => $manifest, 'dir' => $work];
        } catch (\Throwable $e) {
            File::deleteDirectory($work);
            throw $e;
        }
    }

    public function cleanup(string $dir): void
    {
        File::deleteDirectory($dir);
    }

    /** @return list<string> Warnungen/Blocker-Hinweise vor einem Restore */
    public function compatibility(array $manifest): array
    {
        $known = collect(File::files(database_path('migrations')))->map(fn ($f) => $f->getFilenameWithoutExtension());
        $unknown = collect($manifest['migrations'] ?? [])->diff($known);
        $problems = [];
        if ($unknown->isNotEmpty()) {
            $problems[] = 'Die Sicherung enthält Migrationen, die diese App-Version nicht kennt ('.$unknown->first().' …). Bitte zuerst die App aktualisieren.';
        }

        return $problems;
    }

    public function keyMatches(array $manifest): bool
    {
        return hash_equals((string) ($manifest['key_fingerprint'] ?? ''), $this->keyFingerprint());
    }

    /**
     * Spielt eine bereits geprüfte Sicherung ein. Legt vorher automatisch eine Sicherung
     * des aktuellen Stands an (Rollback-Datei) und versucht bei Fehlern, diese zurückzuspielen.
     *
     * @return string Pfad der Sicherheitskopie
     */
    public function restore(string $unpackedDir): string
    {
        $safety = $this->create(preRestore: true);
        Artisan::call('down', ['--retry' => 60]);

        try {
            $this->dumper->restore($unpackedDir.'/pack/db.dump');
            $this->replaceUploads($unpackedDir.'/pack/uploads');
            Artisan::call('migrate', ['--force' => true]); // ältere Sicherung -> aktuelles Schema
        } catch (\Throwable $e) {
            try {
                $opened = $this->open($safety);
                $this->dumper->restore($opened['dir'].'/pack/db.dump');
                $this->replaceUploads($opened['dir'].'/pack/uploads');
                $this->cleanup($opened['dir']);
            } catch (\Throwable $rollbackError) {
                throw new RuntimeException('Restore fehlgeschlagen ('.$e->getMessage().') UND Rollback fehlgeschlagen. Manuell wiederherstellen aus: '.$safety, 0, $e);
            }
            throw new RuntimeException('Restore fehlgeschlagen, vorheriger Stand wurde wiederhergestellt: '.$e->getMessage(), 0, $e);
        } finally {
            Artisan::call('up');
        }

        return $safety;
    }

    /** @return list<array{name: string, size: int, time: int}> neueste zuerst */
    public function list(): array
    {
        return collect(File::files($this->dir()))
            ->filter(fn ($f) => preg_match(self::FILE_PATTERN, $f->getFilename()))
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => $f->getMTime()])
            ->sortByDesc('time')->values()->all();
    }

    public function path(string $name): string
    {
        if (! preg_match(self::FILE_PATTERN, $name) || ! is_file($this->dir().'/'.$name)) {
            throw new NotFoundHttpException('Unbekannte Sicherung.');
        }

        return $this->dir().'/'.$name;
    }

    /** Behält die $keep neuesten regulären Sicherungen (Sicherheitskopien vor Restore bleiben). */
    public function prune(int $keep): int
    {
        $old = collect($this->list())->reject(fn ($b) => str_contains($b['name'], 'pre-restore'))->slice($keep);
        $old->each(fn ($b) => @unlink($this->dir().'/'.$b['name']));

        return $old->count();
    }

    private function counts(): array
    {
        return collect(['users', 'licenses', 'homekit_devices', 'documents', 'reminders'])
            ->filter(fn ($t) => Schema::hasTable($t))
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    /** @return array<string,string> relativer Pfad => sha256 */
    private function copyUploads(string $to): array
    {
        File::ensureDirectoryExists($to);
        $hashes = [];
        $base = $this->uploadsDir();
        foreach (File::allFiles($base) as $f) {
            $rel = $f->getRelativePathname();
            File::ensureDirectoryExists(dirname($to.'/'.$rel));
            copy($f->getPathname(), $to.'/'.$rel);
            $hashes[$rel] = hash_file('sha256', $f->getPathname());
        }

        return $hashes;
    }

    private function replaceUploads(string $from): void
    {
        $base = $this->uploadsDir();
        // Inhalt leeren, aber das Verzeichnis (Volume-Mountpunkt) behalten
        foreach (File::directories($base) as $d) {
            File::deleteDirectory($d);
        }
        foreach (File::files($base) as $f) {
            File::delete($f->getPathname());
        }
        if (is_dir($from)) {
            File::copyDirectory($from, $base);
        }
    }

    private function tempDir(string $prefix): string
    {
        $d = storage_path('app/private/tmp/'.$prefix.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($d, 0700);

        return $d;
    }

    private function exec(array $cmd): string
    {
        $p = new Process($cmd);
        $p->setTimeout(3600);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException($cmd[0].' fehlgeschlagen: '.trim($p->getErrorOutput()));
        }

        return $p->getOutput();
    }
}
