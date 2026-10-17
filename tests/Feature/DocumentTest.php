<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\HomekitDevice;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(string $name = 'Rechnung 2026.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_guests_are_blocked(): void
    {
        Storage::fake('local');
        $this->get('/dokumente')->assertRedirect('/login');
        $this->post('/dokumente', ['file' => $this->pdf()])->assertRedirect('/login');
        $d = $this->makeDoc();
        $this->get("/dokumente/{$d->id}/download")->assertRedirect('/login');
        $this->delete("/dokumente/{$d->id}")->assertRedirect('/login');
    }

    private function makeDoc(?License $l = null): Document
    {
        Storage::disk('local')->put('documents/x.pdf', 'data');
        $d = new Document(['title' => 'T', 'original_name' => 'x.pdf', 'path' => 'documents/x.pdf', 'mime' => 'application/pdf', 'size' => 4]);
        if ($l) {
            $d->attachable()->associate($l);
        }
        $d->save();

        return $d;
    }

    public function test_upload_stores_privately_with_random_name_and_attaches(): void
    {
        Storage::fake('local');
        $l = License::factory()->create();
        $this->actingAs(User::factory()->create())
            ->post('/dokumente', ['file' => $this->pdf(), 'attach' => "license:{$l->id}"])->assertSessionHasNoErrors();

        $d = Document::firstOrFail();
        $this->assertSame('Rechnung 2026', $d->title);
        $this->assertSame('application/pdf', $d->mime);
        $this->assertMatchesRegularExpression('#^uploads/documents/[A-Za-z0-9]{40}\.pdf$#', $d->path);
        Storage::disk('local')->assertExists($d->path);
        $this->assertTrue($l->documents->first()->is($d));
        $this->assertArrayNotHasKey('path', $d->toArray());
    }

    public function test_download_is_attachment_and_not_cacheable(): void
    {
        Storage::fake('local');
        $d = $this->makeDoc();
        $r = $this->actingAs(User::factory()->create())->get("/dokumente/{$d->id}/download")->assertOk();
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
    }

    public function test_dangerous_files_and_bad_targets_are_rejected(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $php = UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;');
        $html = UploadedFile::fake()->createWithContent('x.html', '<script>alert(1)</script>');
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>');
        foreach ([$php, $html, $svg] as $f) {
            $this->actingAs($u)->post('/dokumente', ['file' => $f])->assertSessionHasErrors('file');
        }
        $this->actingAs($u)->post('/dokumente', ['file' => $this->pdf(), 'attach' => 'user:1'])->assertSessionHasErrors('attach');
        $this->actingAs($u)->post('/dokumente', ['file' => $this->pdf(), 'attach' => 'license:999'])->assertNotFound();
        $this->assertSame(0, Document::count());
    }

    public function test_delete_removes_file_and_cascades_from_parent(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $l = License::factory()->create();
        $d = $this->makeDoc($l);
        $this->actingAs($u)->delete("/dokumente/{$d->id}")->assertRedirect();
        Storage::disk('local')->assertMissing('documents/x.pdf');

        $d2 = $this->makeDoc($l);
        $this->actingAs($u)->delete("/lizenzen/{$l->id}")->assertRedirect();
        $this->assertSame(0, Document::count());
        Storage::disk('local')->assertMissing($d2->path);

        $dev = HomekitDevice::factory()->create();
        $d3 = new Document(['title' => 'H', 'original_name' => 'h.pdf', 'path' => 'documents/h.pdf', 'mime' => 'application/pdf', 'size' => 1]);
        Storage::disk('local')->put('documents/h.pdf', 'x');
        $d3->attachable()->associate($dev)->save();
        $this->actingAs($u)->delete("/homekit/{$dev->id}")->assertRedirect();
        Storage::disk('local')->assertMissing('documents/h.pdf');
    }

    public function test_pages_render(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $l = License::factory()->create();
        $this->makeDoc($l);
        $this->actingAs($u)->get('/dokumente')->assertOk()->assertSee('x.pdf');
        $this->actingAs($u)->get("/lizenzen/{$l->id}")->assertOk()->assertSee('Dokumente (1)');
        $this->actingAs($u)->get('/dokumente?q=nomatch')->assertOk()->assertSee('Keine Treffer');
    }
}
