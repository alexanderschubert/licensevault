<?php

namespace App\Backup;

interface DatabaseDumper
{
    /** Schreibt einen Dump der gesamten Datenbank in $file. */
    public function dump(string $file): void;

    /** Stellt die Datenbank aus $file wieder her (ersetzt vorhandene Objekte). */
    public function restore(string $file): void;

    /** Versionsangabe des Dump-Werkzeugs für das Manifest. */
    public function toolVersion(): string;
}
