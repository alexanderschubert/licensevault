<?php

namespace Tests\Support;

use App\Backup\DatabaseDumper;

class FakeDumper implements DatabaseDumper
{
    public static ?string $restored = null;

    public static bool $failRestore = false;

    public function dump(string $file): void
    {
        file_put_contents($file, 'FAKE-DB-DUMP-'.bin2hex(random_bytes(8)));
    }

    public function restore(string $file): void
    {
        if (self::$failRestore) {
            self::$failRestore = false; // nur der erste Versuch schlägt fehl (Rollback muss klappen)
            throw new \RuntimeException('simulierter Fehler');
        }
        self::$restored = file_get_contents($file);
    }

    public function toolVersion(): string
    {
        return 'fake 1.0';
    }
}
