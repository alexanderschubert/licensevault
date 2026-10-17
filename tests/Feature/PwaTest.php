<?php

namespace Tests\Feature;

use App\Models\HomekitDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        foreach ($manifest['icons'] as $icon) {
            $file = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($file);
            [$w, $h] = getimagesize($file);
            $this->assertSame($icon['sizes'], "{$w}x{$h}");
        }
        $this->assertFileExists(public_path('icons/apple-touch-icon-v3.png'));
        $this->assertFileExists(public_path('apple-touch-icon.png')); // Standard-Pfad, den iOS ohne Link-Tag abfragt
        $this->assertSame('/', $manifest['id']);
        $this->assertStringNotContainsString('secret', strtolower(json_encode($manifest)));
    }

    public function test_pages_link_manifest_and_apple_touch_icon(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('rel="manifest"', false)->assertSee('sizes="180x180" href="/icons/apple-touch-icon-v3.png"', false)->assertSee('apple-mobile-web-app-capable', false);
    }

    public function test_homekit_form_offers_camera_scan_and_photo_fallback(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/homekit/create?scan=1')->assertOk()
            ->assertSee('QR-Code mit Kamera scannen')->assertSee('capture="environment"', false)->assertSee('hkScanner', false);
        $this->actingAs($u)->get('/homekit')->assertSee('Scannen');
    }

    public function test_mobile_menu_is_rendered_in_app_layout(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee('Menü öffnen')->assertSee('Abmelden');
    }

    public function test_favicons_exist_and_are_linked(): void
    {
        $this->assertStringContainsString('<svg', file_get_contents(public_path('favicon.svg')));
        $ico = file_get_contents(public_path('favicon.ico'));
        $this->assertSame("\x00\x00\x01\x00", substr($ico, 0, 4));
        $this->assertStringContainsString("\x89PNG", $ico);
        $this->get('/login')->assertSee('href="/favicon.svg"', false)->assertSee('href="/favicon.ico"', false)
            ->assertSee('M12 2.8 4 5.8v6.2', false); // Logo (Schild mit QR-Marken) im Login-Kopf
    }

    public function test_homekit_gallery_offers_edit_and_details_per_card(): void
    {
        $u = User::factory()->create();
        $d = HomekitDevice::factory()->create(['name' => 'Karten-Lampe']);
        $this->actingAs($u)->get('/homekit')->assertSee(route('homekit.edit', $d), false)->assertSee(route('homekit.show', $d), false)->assertSee('Bearbeiten');
    }
}
