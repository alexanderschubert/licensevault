<?php

namespace Tests\Feature;

use App\Models\HomekitDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevicePhotoAndBothTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function payload(array $o = []): array
    {
        return array_merge(['name' => 'Eve Energy', 'status' => 'active', 'protocol' => 'both'], $o);
    }

    private function png(string $name = 'foto.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    public function test_both_accepts_homekit_and_matter_formats_but_not_garbage(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/homekit', $this->payload(['qr_payload' => 'MT:-24J0AFN00KA0648G00', 'setup_code' => '34970112332']))->assertSessionHasNoErrors();
        $this->actingAs($u)->post('/homekit', $this->payload(['name' => 'B', 'qr_payload' => 'X-HM://00527813XTST1', 'setup_code' => '111-22-333']))->assertSessionHasNoErrors();
        $this->actingAs($u)->post('/homekit', $this->payload(['name' => 'C', 'qr_payload' => 'https://evil.test', 'setup_code' => '1234']))->assertSessionHasErrors(['qr_payload', 'setup_code']);
        $this->assertSame(2, HomekitDevice::count());
        $matter = HomekitDevice::where('name', 'Eve Energy')->first();
        $this->assertSame('3497-011-2332', $matter->setup_code); // Matter-Code wird formatiert
        $this->assertSame('111-22-333', HomekitDevice::where('name', 'B')->first()->setup_code);
    }

    public function test_both_shows_both_pills_and_is_found_by_either_filter(): void
    {
        $u = User::factory()->create();
        HomekitDevice::factory()->create(['name' => 'Nur HK']);
        HomekitDevice::factory()->create(['name' => 'Nur MT', 'protocol' => 'matter', 'setup_code' => '34970112332', 'qr_payload' => 'MT:-24J0AFN00KA0648G00']);
        HomekitDevice::factory()->create(['name' => 'Beides', 'protocol' => 'both']);

        $this->actingAs($u)->get('/homekit?protocol=homekit')->assertSee('Nur HK')->assertSee('Beides')->assertDontSee('Nur MT');
        $this->actingAs($u)->get('/homekit?protocol=matter')->assertSee('Nur MT')->assertSee('Beides')->assertDontSee('Nur HK');
        $this->actingAs($u)->get('/homekit?protocol=both')->assertSee('Beides')->assertDontSee('Nur HK');
        $this->actingAs($u)->get('/homekit/create')->assertSee('HomeKit + Matter')->assertSee('Art des Geräts');
    }

    public function test_photo_upload_is_private_replaced_removed_and_cleaned_up(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $this->actingAs($u)->post('/homekit', $this->payload(['protocol' => 'homekit', 'photo' => $this->png()]))->assertSessionHasNoErrors();
        $d = HomekitDevice::firstOrFail();
        $path = DB::table('homekit_devices')->where('id', $d->id)->value('photo_path');
        $this->assertMatchesRegularExpression('#^uploads/device-photos/[A-Za-z0-9]{40}\.png$#', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertArrayNotHasKey('photo_path', $d->toArray());

        $r = $this->actingAs($u)->get("/homekit/{$d->id}/foto")->assertOk();
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->actingAs($u)->get('/homekit')->assertSee("/homekit/{$d->id}/foto", false);
        $this->actingAs($u)->get("/homekit/{$d->id}")->assertSee("/homekit/{$d->id}/foto", false);

        $this->actingAs($u)->put("/homekit/{$d->id}", $this->payload(['protocol' => 'homekit', 'photo' => $this->png('neu.png')]))->assertRedirect();
        Storage::disk('local')->assertMissing($path);
        $new = DB::table('homekit_devices')->where('id', $d->id)->value('photo_path');

        $this->actingAs($u)->put("/homekit/{$d->id}", $this->payload(['protocol' => 'homekit', 'clear_photo' => '1']))->assertRedirect();
        Storage::disk('local')->assertMissing($new);
        $this->actingAs($u)->get("/homekit/{$d->id}/foto")->assertNotFound();

        $this->actingAs($u)->put("/homekit/{$d->id}", $this->payload(['protocol' => 'homekit', 'photo' => $this->png()]));
        $last = DB::table('homekit_devices')->where('id', $d->id)->value('photo_path');
        $this->actingAs($u)->delete("/homekit/{$d->id}");
        Storage::disk('local')->assertMissing($last);

        $this->app['auth']->guard()->logout();
        $this->get('/homekit/1/foto')->assertRedirect('/login');
    }

    public function test_dangerous_photos_are_rejected(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        foreach ([
            UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>'),
            UploadedFile::fake()->createWithContent('x.php', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;'),
            UploadedFile::fake()->create('big.png', 5000),
        ] as $f) {
            $this->actingAs($u)->post('/homekit', $this->payload(['photo' => $f]))->assertSessionHasErrors('photo');
        }
        $this->assertSame(0, HomekitDevice::count());
    }

    private function heic(string $name = 'IMG_0001.HEIC'): UploadedFile
    {
        // minimale ISO-BMFF-Kennung eines HEIC-Bildes (ftyp "heic")
        return UploadedFile::fake()->createWithContent($name, "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64));
    }

    public function test_heic_is_accepted_for_photo_qr_image_icon_and_documents(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();

        $this->actingAs($u)->post('/homekit', $this->payload(['protocol' => 'homekit', 'photo' => $this->heic(), 'qr_file' => $this->heic('qr.heic')]))->assertSessionHasNoErrors();
        $d = HomekitDevice::firstOrFail();
        $this->assertTrue($d->hasPhoto());
        $this->assertTrue($d->hasQrImage());
        $this->assertStringEndsWith('.heic', DB::table('homekit_devices')->value('photo_path'));
        $this->actingAs($u)->get("/homekit/{$d->id}/foto")->assertOk();

        $this->actingAs($u)->post('/lizenzen', ['name' => 'HEIC-Icon', 'license_type' => 'other', 'currency' => 'EUR', 'billing_interval' => 'once', 'icon' => $this->heic('icon.heif')])->assertSessionHasNoErrors();
        $this->actingAs($u)->post('/dokumente', ['file' => $this->heic('Beleg.heic')])->assertSessionHasNoErrors();
    }

    public function test_fake_heic_and_oversized_images_are_still_rejected(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $php = UploadedFile::fake()->createWithContent('evil.heic', '<?php echo 1;');
        $this->actingAs($u)->post('/homekit', $this->payload(['photo' => $php]))->assertSessionHasErrors('photo');

        // PNG-Kopf mit 9000 × 10 Pixeln -> über dem Pixel-Limit
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 9000, 10)."\x08\x02\x00\x00\x00".pack('N', 0).pack('N', 0).'IEND'.pack('N', 0);
        $this->actingAs($u)->post('/homekit', $this->payload(['photo' => UploadedFile::fake()->createWithContent('breit.png', $png)]))->assertSessionHasErrors('photo');
        $this->assertSame(0, HomekitDevice::count());
    }
}
