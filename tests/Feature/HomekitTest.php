<?php

namespace Tests\Feature;

use App\Models\HomekitDevice;
use App\Models\User;
use App\Support\SecretVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomekitTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = 'X-HM://000000000TST0';

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Testlampe', 'manufacturer' => 'ACME', 'model' => 'L-1', 'room' => 'Flur',
            'status' => 'active', 'setup_code' => '111-22-333', 'qr_payload' => self::PAYLOAD,
        ], $o);
    }

    public function test_guests_are_blocked_everywhere(): void
    {
        $d = HomekitDevice::factory()->create();
        $this->get('/homekit')->assertRedirect('/login');
        $this->get("/homekit/{$d->id}/qr")->assertRedirect('/login');
        $this->post("/homekit/{$d->id}/code")->assertRedirect('/login');
        $this->get('/homekit/druck')->assertRedirect('/login');
        $this->post('/homekit', $this->payload())->assertRedirect('/login');
    }

    public function test_secrets_are_stored_encrypted(): void
    {
        $this->actingAs(User::factory()->create())->post('/homekit', $this->payload())->assertRedirect();
        $d = HomekitDevice::firstOrFail();
        $row = DB::table('homekit_devices')->where('id', $d->id)->first();
        $this->assertStringNotContainsString('111-22-333', $row->setup_code_ciphertext);
        $this->assertStringNotContainsString('TST0', $row->qr_payload_ciphertext);
        $this->assertSame('111-22-333', SecretVault::decrypt($row->setup_code_ciphertext));
        $this->assertSame(self::PAYLOAD, $d->qr_payload);
        $this->assertArrayNotHasKey('setup_code_ciphertext', $d->toArray());
    }

    public function test_pages_never_contain_secrets_and_are_not_cacheable(): void
    {
        $u = User::factory()->create();
        $d = HomekitDevice::factory()->create(['setup_code' => '999-88-777', 'qr_payload' => 'X-HM://000000000ZZZ9']);
        foreach (['/homekit', "/homekit/{$d->id}", "/homekit/{$d->id}/edit", '/'] as $uri) {
            $r = $this->actingAs($u)->get($uri)->assertOk();
            $r->assertDontSee('999-88-777')->assertDontSee('ZZZ9');
        }
        $this->actingAs($u)->get('/homekit')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->actingAs($u)->post("/homekit/{$d->id}/code")->assertOk()->assertJson(['secret' => '999-88-777'])
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_qr_svg_is_generated_from_payload(): void
    {
        $d = HomekitDevice::factory()->create();
        $r = $this->actingAs(User::factory()->create())->get("/homekit/{$d->id}/qr")->assertOk();
        $this->assertStringContainsString('image/svg+xml', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $r->getContent());
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    public function test_qr_image_upload_is_private_and_removed_with_device(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('qr.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->actingAs($u)->post('/homekit', $this->payload(['qr_payload' => '', 'qr_file' => $file]))->assertRedirect();
        $d = HomekitDevice::firstOrFail();
        $this->assertTrue($d->hasQrImage());
        Storage::disk('local')->assertExists($d->imagePath());
        $this->actingAs($u)->get("/homekit/{$d->id}/qr")->assertOk();
        $this->actingAs($u)->delete("/homekit/{$d->id}")->assertRedirect('/homekit');
        Storage::disk('local')->assertMissing($d->imagePath());
    }

    public function test_upload_rejects_non_images(): void
    {
        Storage::fake('local');
        $bad = UploadedFile::fake()->create('evil.php', 10, 'application/x-php');
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>');
        $u = User::factory()->create();
        $this->actingAs($u)->post('/homekit', $this->payload(['qr_file' => $bad]))->assertSessionHasErrors('qr_file');
        $this->actingAs($u)->post('/homekit', $this->payload(['qr_file' => $svg]))->assertSessionHasErrors('qr_file');
        $this->assertSame(0, HomekitDevice::count());
    }

    public function test_validation_of_codes(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/homekit', $this->payload(['setup_code' => '12345', 'qr_payload' => 'https://evil.test']))
            ->assertSessionHasErrors(['setup_code', 'qr_payload']);
        $this->actingAs($u)->post('/homekit', $this->payload(['name' => '', 'status' => 'bogus']))->assertSessionHasErrors(['name', 'status']);
    }

    public function test_update_keeps_and_clears_secrets(): void
    {
        $u = User::factory()->create();
        $d = HomekitDevice::factory()->create(['setup_code' => '123-45-678']);
        $this->actingAs($u)->put("/homekit/{$d->id}", $this->payload(['setup_code' => '', 'qr_payload' => '', 'name' => 'Neu']))->assertRedirect();
        $this->assertSame('123-45-678', $d->fresh()->setup_code);
        $this->actingAs($u)->put("/homekit/{$d->id}", $this->payload(['setup_code' => '', 'qr_payload' => '', 'clear_setup_code' => '1', 'clear_qr' => '1']))->assertRedirect();
        $this->assertFalse($d->fresh()->hasSetupCode());
        $this->assertFalse($d->fresh()->hasQr());
    }

    public function test_search_room_filter_and_print_view(): void
    {
        $u = User::factory()->create();
        HomekitDevice::factory()->create(['name' => 'Alpha Lampe', 'room' => 'Küche']);
        HomekitDevice::factory()->create(['name' => 'Beta Sensor', 'room' => 'Büro']);
        $this->actingAs($u)->get('/homekit?q=alpha')->assertSee('Alpha Lampe')->assertDontSee('Beta Sensor');
        $this->actingAs($u)->get('/homekit?room=B%C3%BCro')->assertSee('Beta Sensor')->assertDontSee('Alpha Lampe');
        $this->actingAs($u)->get('/homekit/druck?room=K%C3%BCche')->assertOk()->assertSee('Alpha Lampe')->assertDontSee('Beta Sensor');
    }
}
