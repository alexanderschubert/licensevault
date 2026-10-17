<?php

namespace App\Http\Controllers;

use App\Backup\BackupService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index()
    {
        return view('backup.index', [
            'backups' => $this->backups->list(),
            'configured' => mb_strlen((string) config('licensevault.backup.passphrase')) >= 12,
            'keep' => config('licensevault.backup.keep'),
            'time' => config('licensevault.backup.time'),
        ]);
    }

    public function create(): RedirectResponse
    {
        try {
            $path = $this->backups->create();
            $this->backups->prune((int) config('licensevault.backup.keep'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => 'Sicherung fehlgeschlagen: '.$e->getMessage()]);
        }

        return back()->with('status', 'Sicherung erstellt: '.basename($path));
    }

    public function verify(string $name): RedirectResponse
    {
        try {
            $opened = $this->backups->open($this->backups->path($name));
            $m = $opened['manifest'];
            $this->backups->cleanup($opened['dir']);
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => "Prüfung von {$name} fehlgeschlagen: ".$e->getMessage()]);
        }

        $key = $this->backups->keyMatches($m) ? 'APP_KEY passt' : 'ACHTUNG: APP_KEY weicht ab';

        return back()->with('status', "{$name}: intakt (erstellt {$m['created_at']}, {$key}).");
    }

    public function download(string $name): BinaryFileResponse
    {
        return response()->download($this->backups->path($name), $name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(string $name): RedirectResponse
    {
        @unlink($this->backups->path($name));

        return back()->with('status', 'Sicherung gelöscht.');
    }
}
