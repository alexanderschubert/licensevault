<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LicenseExtrasTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'CleanMyMac 5', 'license_type' => 'subscription', 'currency' => 'EUR', 'billing_interval' => 'yearly',
        ], $o);
    }

    private function png(string $name = 'icon.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    public function test_platforms_and_devices_are_stored_shown_and_filterable(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/lizenzen', $this->payload([
            'platforms' => ['macos', 'ios'], 'devices' => 'MacBook Pro, iPhone, macbook pro',
        ]))->assertRedirect();

        $l = License::firstOrFail();
        $this->assertSame(['macos', 'ios'], $l->platforms);
        $this->assertSame(['MacBook Pro', 'iPhone'], $l->devices->pluck('name')->sort()->values()->all());

        $this->actingAs($u)->get("/lizenzen/{$l->id}")->assertSee('macOS')->assertSee('iOS / iPadOS')->assertSee('MacBook Pro');
        License::factory()->create(['name' => 'Nur Windows', 'platforms' => ['windows']]);
        $this->actingAs($u)->get('/lizenzen?platform=macos')->assertSee('CleanMyMac 5')->assertDontSee('Nur Windows');
        $deviceId = Device::where('name', 'iPhone')->value('id');
        $this->actingAs($u)->get("/lizenzen?device={$deviceId}")->assertSee('CleanMyMac 5')->assertDontSee('Nur Windows');
        $this->actingAs($u)->get('/lizenzen?q=iphone')->assertSee('CleanMyMac 5');

        // Plattform abwählen entfernt sie, Geräte neu setzen
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['platforms' => ['macos'], 'devices' => 'Mac mini']))->assertRedirect();
        $this->assertSame(['macos'], $l->fresh()->platforms);
        $this->assertSame(['Mac mini'], $l->fresh()->devices->pluck('name')->all());
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload())->assertRedirect();
        $this->assertNull($l->fresh()->platforms);
    }

    public function test_invalid_platform_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post('/lizenzen', $this->payload(['platforms' => ['amiga']]))->assertSessionHasErrors('platforms.0');
    }

    public function test_tiles_are_the_default_and_the_choice_is_remembered(): void
    {
        $u = User::factory()->create();
        $l = License::factory()->create(['name' => 'Kachel-Lizenz']);

        $this->actingAs($u)->get('/lizenzen')->assertOk()->assertSee('Kachel-Lizenz')->assertDontSee('<table', false)->assertSee(route('licenses.edit', $l), false);

        // Listenansicht wählen -> Cookie merkt sie sich (auch für spätere Aufrufe ohne ?view)
        $r = $this->actingAs($u)->get('/lizenzen?view=list')->assertSee('<table', false);
        $cookie = collect($r->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'licenses_view');
        $this->assertNotNull($cookie);
        $this->assertGreaterThan(time() + 300 * 86400, $cookie->getExpiresTime());

        $this->withUnencryptedCookie('licenses_view', 'list')->actingAs($u)->get('/lizenzen')->assertSee('<table', false);
        $this->withUnencryptedCookie('licenses_view', 'tiles')->actingAs($u)->get('/lizenzen')->assertDontSee('<table', false);
        $this->withUnencryptedCookie('licenses_view', 'evil')->actingAs($u)->get('/lizenzen')->assertDontSee('<table', false); // ungültig -> Standard
        $this->actingAs($u)->get('/lizenzen?view=evil')->assertSessionHasErrors('view');
    }

    public function test_icon_upload_is_served_only_to_logged_in_and_cleaned_up(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $this->actingAs($u)->post('/lizenzen', $this->payload(['icon' => $this->png()]))->assertSessionHasNoErrors();
        $l = License::firstOrFail();
        $this->assertMatchesRegularExpression('#^uploads/icons/[A-Za-z0-9]{40}\.png$#', DB::table('licenses')->where('id', $l->id)->value('icon_path'));
        $old = DB::table('licenses')->where('id', $l->id)->value('icon_path');
        Storage::disk('local')->assertExists($old);

        $r = $this->actingAs($u)->get("/lizenzen/{$l->id}/icon")->assertOk();
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertArrayNotHasKey('icon_path', $l->fresh()->toArray());
        $this->actingAs($u)->get('/lizenzen')->assertSee("/lizenzen/{$l->id}/icon", false);

        // ersetzen löscht die alte Datei
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['icon' => $this->png('neu.png')]))->assertRedirect();
        Storage::disk('local')->assertMissing($old);
        $new = DB::table('licenses')->where('id', $l->id)->value('icon_path');
        Storage::disk('local')->assertExists($new);

        // entfernen
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['clear_icon' => '1']))->assertRedirect();
        Storage::disk('local')->assertMissing($new);
        $this->actingAs($u)->get("/lizenzen/{$l->id}/icon")->assertNotFound();

        // löschen der Lizenz räumt das Icon auf
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['icon' => $this->png()]));
        $path = DB::table('licenses')->where('id', $l->id)->value('icon_path');
        $this->actingAs($u)->delete("/lizenzen/{$l->id}");
        Storage::disk('local')->assertMissing($path);

        $this->app['auth']->guard()->logout();
        $this->get('/lizenzen/1/icon')->assertRedirect('/login');
    }

    public function test_dangerous_icons_are_rejected(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        foreach ([
            UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>'),
            UploadedFile::fake()->createWithContent('x.php', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;'),
            UploadedFile::fake()->create('big.png', 600),
        ] as $f) {
            $this->actingAs($u)->post('/lizenzen', $this->payload(['icon' => $f]))->assertSessionHasErrors('icon');
        }
        $this->assertSame(0, License::count());
    }

    public function test_recovery_codes_customer_number_and_support(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/lizenzen', $this->payload([
            'recovery_codes' => "AAAA-1111\nBBBB-2222", 'customer_number' => 'K-4711', 'support_contact' => 'https://example.test/help',
        ]))->assertRedirect();
        $l = License::firstOrFail();
        $raw = DB::table('licenses')->where('id', $l->id)->value('recovery_codes_ciphertext');
        $this->assertStringNotContainsString('AAAA-1111', $raw);
        $this->assertArrayNotHasKey('recovery_codes_ciphertext', $l->toArray());

        foreach (['/lizenzen', "/lizenzen/{$l->id}", "/lizenzen/{$l->id}/edit"] as $uri) {
            $this->actingAs($u)->get($uri)->assertOk()->assertDontSee('AAAA-1111');
        }
        $this->actingAs($u)->get("/lizenzen/{$l->id}")->assertSee('K-4711')->assertSee('https://example.test/help');
        $this->actingAs($u)->post("/lizenzen/{$l->id}/wiederherstellung")->assertOk()
            ->assertJson(['secret' => "AAAA-1111\nBBBB-2222"])->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['recovery_codes' => '']))->assertRedirect();
        $this->assertTrue($l->fresh()->hasRecoveryCodes());
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['clear_recovery_codes' => '1']))->assertRedirect();
        $this->assertFalse($l->fresh()->hasRecoveryCodes());
        $this->app['auth']->guard()->logout();
        $this->post("/lizenzen/{$l->id}/wiederherstellung")->assertRedirect('/login');
    }

    public function test_csv_contains_new_columns_but_no_secrets(): void
    {
        $u = User::factory()->create();
        $l = License::factory()->create(['platforms' => ['macos'], 'recovery_codes' => 'GEHEIM-CODE', 'customer_number' => 'K-1']);
        $l->devices()->sync([Device::create(['name' => 'Studio-Mac'])->id]);
        $csv = $this->actingAs($u)->get('/export/lizenzen.csv')->streamedContent();
        $this->assertStringContainsString('macOS', $csv);
        $this->assertStringContainsString('Studio-Mac', $csv);
        $this->assertStringContainsString('K-1', $csv);
        $this->assertStringNotContainsString('GEHEIM-CODE', $csv);
    }
}
