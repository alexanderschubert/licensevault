<?php

namespace Tests\Feature;

use App\Models\HomekitDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_endpoints_require_login(): void
    {
        $d = HomekitDevice::factory()->create();
        $this->get("/homekit/{$d->id}/pdf")->assertRedirect('/login');
        $this->get('/homekit/druck.pdf')->assertRedirect('/login');
    }

    public function test_single_device_pdf_is_a_private_pdf_without_the_payload_text(): void
    {
        $d = HomekitDevice::factory()->create(['name' => 'Wohnzimmerlampe', 'qr_payload' => 'X-HM://000000000PDF1']);
        $r = $this->actingAs(User::factory()->create())->get("/homekit/{$d->id}/pdf")->assertOk();

        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $r->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertStringNotContainsString('PDF1', $r->getContent()); // Payload nur als Grafik, nie als Text
    }

    public function test_list_pdf_respects_filters_and_handles_empty_results(): void
    {
        $u = User::factory()->create();
        HomekitDevice::factory()->create(['name' => 'Küche A', 'room' => 'Küche']);
        HomekitDevice::factory()->create(['name' => 'Büro B', 'room' => 'Büro']);

        $all = $this->actingAs($u)->get('/homekit/druck.pdf')->assertOk()->getContent();
        $one = $this->actingAs($u)->get('/homekit/druck.pdf?room=B%C3%BCro')->assertOk()->getContent();
        $none = $this->actingAs($u)->get('/homekit/druck.pdf?room=Garage')->assertOk()->getContent();
        foreach ([$all, $one, $none] as $pdf) {
            $this->assertStringStartsWith('%PDF', $pdf);
        }
        $this->assertGreaterThan(strlen($one), strlen($all));
        $this->actingAs($u)->get('/homekit/druck.pdf?protocol=evil')->assertSessionHasErrors('protocol');
    }

    public function test_device_without_qr_has_no_pdf_and_buttons_are_present(): void
    {
        $u = User::factory()->create();
        $bare = HomekitDevice::factory()->create(['qr_payload' => null]);
        $this->actingAs($u)->get("/homekit/{$bare->id}/pdf")->assertNotFound();

        $with = HomekitDevice::factory()->create();
        $this->actingAs($u)->get("/homekit/{$with->id}")->assertSee(route('homekit.pdf', $with), false)->assertSee('lvPrint', false);
        $this->actingAs($u)->get('/homekit')->assertSee(route('homekit.print.pdf'), false)->assertSee('PDF');
        $this->actingAs($u)->get('/homekit/druck')->assertSee('Als PDF')->assertSee('lvPrint', false);
    }
}
