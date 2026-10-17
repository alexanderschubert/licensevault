<?php

namespace Tests\Feature;

use App\Models\HomekitDevice;
use App\Models\User;
use App\Support\Matter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterTest extends TestCase
{
    use RefreshDatabase;

    private const QR = 'MT:-24J0AFN00KA0648G00'; // Matter-SDK-Testvektor

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Matter-Steckdose', 'status' => 'active', 'protocol' => 'matter',
            'setup_code' => '34970112332', 'qr_payload' => self::QR,
        ], $o);
    }

    public function test_qr_payload_and_manual_code_match_the_sdk_test_vector(): void
    {
        $p = Matter::parseQr(self::QR);
        $this->assertSame(['version' => 0, 'vendorId' => 0xFFF1, 'productId' => 0x8001, 'flow' => 0, 'capabilities' => 4, 'discriminator' => 3840, 'passcode' => 20202021], $p);
        $this->assertSame('34970112332', Matter::manualCode($p['discriminator'], $p['passcode']));
        $this->assertTrue(Matter::validManualCode('3497-011-2332'));
        $this->assertTrue(Matter::validManualCode('34970112332'));
        $this->assertFalse(Matter::validManualCode('34970112333')); // Prüfziffer falsch
        $this->assertFalse(Matter::validManualCode('3497011233'));  // zu kurz
        $this->assertFalse(Matter::validManualCode('3497a112332'));
        $this->assertSame('3497-011-2332', Matter::formatManual('34970112332'));
        $this->assertNull(Matter::parseQr('MT:SHORT'));
        $this->assertNull(Matter::parseQr('X-HM://00527813XTST1'));
        $this->assertFalse(Matter::validQr('MT:'.str_repeat('Z', 19)));
    }

    public function test_matter_device_is_stored_encrypted_formatted_and_renders_a_qr(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/homekit', $this->payload(['qr_payload' => strtolower(self::QR)]))->assertSessionHasNoErrors()->assertRedirect();

        $d = HomekitDevice::firstOrFail();
        $this->assertSame('matter', $d->protocol->value);
        $this->assertSame(self::QR, $d->qr_payload);
        $this->assertSame('3497-011-2332', $d->setup_code);
        $this->assertStringNotContainsString('3497', (string) \DB::table('homekit_devices')->value('setup_code_ciphertext'));

        $r = $this->actingAs($u)->get("/homekit/{$d->id}/qr")->assertOk();
        $this->assertStringContainsString('<svg', $r->getContent());
        $this->actingAs($u)->post("/homekit/{$d->id}/code")->assertJson(['secret' => '3497-011-2332']);
        $this->actingAs($u)->get("/homekit/{$d->id}")->assertOk()->assertSee('Matter')->assertSee('Manueller Kopplungscode')
            ->assertDontSee('3497-011-2332')->assertDontSee('24J0AFN');
    }

    public function test_formats_are_validated_per_protocol(): void
    {
        $u = User::factory()->create();
        // HomeKit-Daten bei Matter und umgekehrt werden abgelehnt
        $this->actingAs($u)->post('/homekit', $this->payload(['qr_payload' => 'X-HM://00527813XTST1', 'setup_code' => '111-22-333']))
            ->assertSessionHasErrors(['qr_payload', 'setup_code']);
        $this->actingAs($u)->post('/homekit', $this->payload(['protocol' => 'homekit']))->assertSessionHasErrors(['qr_payload', 'setup_code']);
        $this->actingAs($u)->post('/homekit', $this->payload(['setup_code' => '34970112399']))->assertSessionHasErrors('setup_code');
        $this->actingAs($u)->post('/homekit', $this->payload(['protocol' => 'zigbee']))->assertSessionHasErrors('protocol');
        $this->assertSame(0, HomekitDevice::count());

        // HomeKit funktioniert unverändert, auch ohne Protokoll-Feld (Abwärtskompatibilität)
        $this->actingAs($u)->post('/homekit', ['name' => 'Alt', 'status' => 'active', 'setup_code' => '111-22-333', 'qr_payload' => 'X-HM://00527813XTST1'])->assertSessionHasNoErrors();
        $this->assertSame('homekit', HomekitDevice::firstOrFail()->protocol->value);
    }

    public function test_filter_by_protocol_and_csv(): void
    {
        $u = User::factory()->create();
        HomekitDevice::factory()->create(['name' => 'HK-Lampe']);
        HomekitDevice::factory()->create(['name' => 'MT-Sensor', 'protocol' => 'matter', 'setup_code' => '34970112332', 'qr_payload' => self::QR]);

        $this->actingAs($u)->get('/homekit?protocol=matter')->assertSee('MT-Sensor')->assertDontSee('HK-Lampe');
        $this->actingAs($u)->get('/homekit?protocol=homekit')->assertSee('HK-Lampe')->assertDontSee('MT-Sensor');
        $this->actingAs($u)->get('/homekit?protocol=evil')->assertSessionHasErrors('protocol');
        $csv = $this->actingAs($u)->get('/export/homekit.csv')->streamedContent();
        $this->assertStringContainsString('Matter', $csv);
        $this->assertStringNotContainsString('3497', $csv);
    }

    public function test_form_offers_protocol_switch(): void
    {
        $this->actingAs(User::factory()->create())->get('/homekit/create')->assertOk()
            ->assertSee('name="protocol"', false)->assertSee('Matter')->assertSee('MT:…', false);
    }
}
