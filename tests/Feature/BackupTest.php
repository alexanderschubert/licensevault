<?php

namespace Tests\Feature;

use App\Backup\BackupCrypto;
use App\Backup\BackupService;
use App\Backup\DatabaseDumper;
use App\Models\HomekitDevice;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeDumper;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/lvtest-'.bin2hex(random_bytes(5));
        mkdir($this->tmp, 0700, true);
        $this->app->useStoragePath($this->tmp);
        config(['licensevault.backup.passphrase' => 'test-passphrase-123456']);
        $this->app->bind(DatabaseDumper::class, FakeDumper::class);
        FakeDumper::$restored = null;
        FakeDumper::$failRestore = false;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    private function svc(): BackupService
    {
        return $this->app->make(BackupService::class);
    }

    private function putUpload(string $rel, string $content): void
    {
        $p = $this->svc()->uploadsDir().'/'.$rel;
        File::ensureDirectoryExists(dirname($p));
        file_put_contents($p, $content);
    }

    public function test_crypto_roundtrip_wrong_passphrase_and_tampering(): void
    {
        $plain = $this->tmp.'/p.bin';
        $data = random_bytes(3 * 1048576 + 123); // mehrere Blöcke
        file_put_contents($plain, $data);
        BackupCrypto::encryptFile($plain, $this->tmp.'/e.bin', 'correct-horse-battery');
        $this->assertStringNotContainsString(substr($data, 0, 32), file_get_contents($this->tmp.'/e.bin'));

        BackupCrypto::decryptFile($this->tmp.'/e.bin', $this->tmp.'/d.bin', 'correct-horse-battery');
        $this->assertSame(hash('sha256', $data), hash_file('sha256', $this->tmp.'/d.bin'));

        try {
            BackupCrypto::decryptFile($this->tmp.'/e.bin', $this->tmp.'/x.bin', 'wrong-passphrase-xx');
            $this->fail('Falsche Passphrase wurde akzeptiert');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Passphrase', $e->getMessage());
        }

        $raw = file_get_contents($this->tmp.'/e.bin');
        $raw[200] = $raw[200] ^ "\x01";
        file_put_contents($this->tmp.'/t.bin', $raw);
        $this->expectException(\RuntimeException::class);
        BackupCrypto::decryptFile($this->tmp.'/t.bin', $this->tmp.'/y.bin', 'correct-horse-battery');
    }

    public function test_truncated_backup_is_rejected(): void
    {
        file_put_contents($this->tmp.'/p.bin', random_bytes(2 * 1048576));
        BackupCrypto::encryptFile($this->tmp.'/p.bin', $this->tmp.'/e.bin', 'correct-horse-battery');
        $raw = file_get_contents($this->tmp.'/e.bin');
        file_put_contents($this->tmp.'/cut.bin', substr($raw, 0, intdiv(strlen($raw), 2)));
        $this->expectException(\RuntimeException::class);
        BackupCrypto::decryptFile($this->tmp.'/cut.bin', $this->tmp.'/z.bin', 'correct-horse-battery');
    }

    public function test_short_passphrase_is_refused(): void
    {
        config(['licensevault.backup.passphrase' => 'kurz']);
        $this->expectException(\RuntimeException::class);
        $this->svc()->create();
    }

    public function test_backup_contains_manifest_and_is_encrypted(): void
    {
        License::factory()->create();
        $this->putUpload('documents/a.pdf', 'GEHEIMER-INHALT-ABC');
        $path = $this->svc()->create();

        $this->assertMatchesRegularExpression(BackupService::FILE_PATTERN, basename($path));
        $this->assertStringNotContainsString('GEHEIMER-INHALT-ABC', file_get_contents($path));
        $this->assertStringNotContainsString('licensevault', substr(file_get_contents($path), 60, 400));

        $opened = $this->svc()->open($path);
        $m = $opened['manifest'];
        $this->assertSame(1, $m['counts']['licenses']);
        $this->assertArrayHasKey('documents/a.pdf', $m['files']);
        $this->assertTrue($this->svc()->keyMatches($m));
        $this->assertSame([], $this->svc()->compatibility($m));
        $this->svc()->cleanup($opened['dir']);
    }

    public function test_restore_replaces_uploads_and_database_and_creates_safety_copy(): void
    {
        $this->putUpload('documents/a.pdf', 'ORIGINAL');
        $backup = $this->svc()->create();
        $dump = file_get_contents($this->svc()->open($backup)['dir'].'/pack/db.dump');

        $this->putUpload('documents/a.pdf', 'GEAENDERT');
        $this->putUpload('documents/neu.pdf', 'NEU');

        $opened = $this->svc()->open($backup);
        $safety = $this->svc()->restore($opened['dir']);

        $this->assertSame('ORIGINAL', file_get_contents($this->svc()->uploadsDir().'/documents/a.pdf'));
        $this->assertFileDoesNotExist($this->svc()->uploadsDir().'/documents/neu.pdf');
        $this->assertSame($dump, FakeDumper::$restored);
        $this->assertStringContainsString('pre-restore', basename($safety));
        $this->assertFileExists($safety);
    }

    public function test_failed_restore_rolls_back_to_previous_state(): void
    {
        $this->putUpload('documents/a.pdf', 'ALT');
        $backup = $this->svc()->create();
        $this->putUpload('documents/a.pdf', 'AKTUELL');

        FakeDumper::$failRestore = true;
        $opened = $this->svc()->open($backup);
        try {
            $this->svc()->restore($opened['dir']);
            $this->fail('Restore hätte fehlschlagen müssen');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('wiederhergestellt', $e->getMessage());
        }
        $this->assertSame('AKTUELL', file_get_contents($this->svc()->uploadsDir().'/documents/a.pdf'));
    }

    public function test_tampered_file_inside_backup_is_detected_via_checksums(): void
    {
        $this->putUpload('documents/a.pdf', 'X');
        $good = $this->svc()->create();
        $opened = $this->svc()->open($good);
        // Datei im entpackten Stand manipulieren und neu verpacken+verschlüsseln
        file_put_contents($opened['dir'].'/pack/uploads/documents/a.pdf', 'MANIPULIERT');
        shell_exec('tar -czf '.escapeshellarg($this->tmp.'/m.tar.gz').' -C '.escapeshellarg($opened['dir'].'/pack').' .');
        BackupCrypto::encryptFile($this->tmp.'/m.tar.gz', $this->svc()->dir().'/licensevault-20260101-000000.lvbak', 'test-passphrase-123456');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Prüfsumme');
        $this->svc()->open($this->svc()->dir().'/licensevault-20260101-000000.lvbak');
    }

    public function test_key_mismatch_and_unknown_migrations_are_flagged(): void
    {
        $m = ['key_fingerprint' => 'abcdefabcdef', 'migrations' => ['2099_01_01_000000_future_table']];
        $this->assertFalse($this->svc()->keyMatches($m));
        $this->assertNotEmpty($this->svc()->compatibility($m));
    }

    public function test_prune_keeps_newest_and_safety_copies(): void
    {
        foreach (['20260101-000001', '20260102-000001', '20260103-000001'] as $i => $ts) {
            file_put_contents($this->svc()->dir()."/licensevault-{$ts}.lvbak", 'x');
            touch($this->svc()->dir()."/licensevault-{$ts}.lvbak", 1000 + $i);
        }
        file_put_contents($this->svc()->dir().'/licensevault-20250101-000001-pre-restore.lvbak', 'x');
        $this->assertSame(2, $this->svc()->prune(1));
        $names = collect($this->svc()->list())->pluck('name')->all();
        $this->assertContains('licensevault-20260103-000001.lvbak', $names);
        $this->assertContains('licensevault-20250101-000001-pre-restore.lvbak', $names);
    }

    public function test_command_dry_run_and_key_mismatch_refusal(): void
    {
        $this->putUpload('documents/a.pdf', 'X');
        $this->artisan('licensevault:backup')->assertSuccessful();
        $name = $this->svc()->list()[0]['name'];
        $this->artisan('licensevault:restore', ['file' => $name, '--dry-run' => true])->assertSuccessful();
        $this->assertNull(FakeDumper::$restored);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->artisan('licensevault:restore', ['file' => $name, '--force' => true])->assertFailed();
        $this->assertNull(FakeDumper::$restored);
        $this->artisan('licensevault:restore', ['file' => $name, '--force' => true, '--ignore-key-mismatch' => true])->assertSuccessful();
        $this->assertNotNull(FakeDumper::$restored);
    }

    public function test_ui_requires_login_and_validates_filenames(): void
    {
        $this->get('/backup')->assertRedirect('/login');
        $this->post('/backup')->assertRedirect('/login');
        $this->get('/export/lizenzen.csv')->assertRedirect('/login');

        $u = User::factory()->create();
        $this->actingAs($u)->get('/backup')->assertOk();
        $this->actingAs($u)->post('/backup')->assertRedirect();
        $name = $this->svc()->list()[0]['name'];
        $r = $this->actingAs($u)->get("/backup/{$name}/download")->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->actingAs($u)->get('/backup/..%2F..%2F.env/download')->assertNotFound();
        $this->actingAs($u)->get('/backup/evil.lvbak/download')->assertNotFound();
        $this->actingAs($u)->post("/backup/{$name}/pruefen")->assertSessionHas('status');
        $this->actingAs($u)->delete("/backup/{$name}")->assertRedirect();
        $this->assertSame([], $this->svc()->list());
    }

    public function test_csv_export_excludes_secrets_and_neutralizes_formulas(): void
    {
        License::factory()->create(['name' => '=HYPERLINK("http://evil")', 'secret' => 'TOPSECRET-123', 'notes' => '@cmd']);
        HomekitDevice::factory()->create(['setup_code' => '555-55-555', 'qr_payload' => 'X-HM://000000000XXX1']);
        $u = User::factory()->create();

        $csv = $this->actingAs($u)->get('/export/lizenzen.csv')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('TOPSECRET-123', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@cmd", $csv);

        $hk = $this->actingAs($u)->get('/export/homekit.csv')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('555-55-555', $hk);
        $this->assertStringNotContainsString('XXX1', $hk);
    }
}
