<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use App\Support\SecretVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Test Suite', 'vendor' => 'ACME', 'license_type' => 'subscription',
            'secret' => 'ABCD-1234-SECRET', 'currency' => 'EUR', 'billing_interval' => 'monthly', 'purchase_price' => '49.90',
            'expires_at' => today()->addDays(20)->format('Y-m-d'), 'category' => 'Entwicklung', 'tags' => 'ide, Arbeit, ide',
        ], $o);
    }

    public function test_guests_cannot_access_licenses(): void
    {
        $l = License::factory()->create();
        $this->get('/lizenzen')->assertRedirect('/login');
        $this->post('/lizenzen', $this->payload())->assertRedirect('/login');
        $this->post("/lizenzen/{$l->id}/schluessel")->assertRedirect('/login');
        $this->delete("/lizenzen/{$l->id}")->assertRedirect('/login');
    }

    public function test_create_stores_secret_encrypted_and_syncs_tags(): void
    {
        $this->actingAs(User::factory()->create())->post('/lizenzen', $this->payload())->assertRedirect();
        $l = License::firstOrFail();

        $raw = DB::table('licenses')->where('id', $l->id)->value('secret_ciphertext');
        $this->assertStringNotContainsString('ABCD-1234-SECRET', $raw);
        $this->assertStringStartsWith('v1:', $raw);
        $this->assertSame('ABCD-1234-SECRET', SecretVault::decrypt($raw));
        $this->assertSame(['Arbeit', 'ide'], $l->tags->pluck('name')->sort()->values()->all());
        $this->assertSame('Entwicklung', $l->category->name);
        $this->assertArrayNotHasKey('secret_ciphertext', $l->toArray());
    }

    public function test_secret_is_never_rendered_in_pages_only_via_reveal(): void
    {
        $u = User::factory()->create();
        $l = License::factory()->create(['secret' => 'TOP-SECRET-KEY-1']);
        foreach (['/lizenzen', "/lizenzen/{$l->id}", "/lizenzen/{$l->id}/edit", '/'] as $uri) {
            $this->actingAs($u)->get($uri)->assertOk()->assertDontSee('TOP-SECRET-KEY-1');
        }
        $this->actingAs($u)->post("/lizenzen/{$l->id}/schluessel")->assertOk()
            ->assertJson(['secret' => 'TOP-SECRET-KEY-1'])
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_multiple_license_numbers_are_stored_encrypted_and_revealed_together(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/lizenzen', $this->payload(['secret' => "AAAA-1111\nBBBB-2222\nCCCC-3333"]))->assertRedirect();
        $l = License::firstOrFail();
        $this->assertStringNotContainsString('BBBB-2222', DB::table('licenses')->where('id', $l->id)->value('secret_ciphertext'));
        $this->actingAs($u)->get("/lizenzen/{$l->id}")->assertOk()->assertDontSee('BBBB-2222');
        $this->actingAs($u)->post("/lizenzen/{$l->id}/schluessel")->assertOk()->assertJson(['secret' => "AAAA-1111\nBBBB-2222\nCCCC-3333"]);
    }

    public function test_update_keeps_secret_when_blank_and_can_clear(): void
    {
        $u = User::factory()->create();
        $l = License::factory()->create(['secret' => 'KEEP-ME']);
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['secret' => '', 'name' => 'Neu']))->assertRedirect();
        $this->assertSame('KEEP-ME', $l->fresh()->secret);
        $this->assertSame('Neu', $l->fresh()->name);
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['secret' => '', 'clear_secret' => '1']))->assertRedirect();
        $this->assertFalse($l->fresh()->hasSecret());
    }

    public function test_validation(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/lizenzen', ['name' => '', 'license_type' => 'bogus', 'currency' => 'EURO', 'purchase_price' => 'abc'])
            ->assertSessionHasErrors(['name', 'license_type', 'currency', 'purchase_price']);
        $this->assertSame(0, License::count());
    }

    public function test_delete(): void
    {
        $l = License::factory()->create();
        $this->actingAs(User::factory()->create())->delete("/lizenzen/{$l->id}")->assertRedirect('/lizenzen');
        $this->assertSame(0, License::count());
    }

    public function test_search_and_filters(): void
    {
        $u = User::factory()->create();
        License::factory()->create(['name' => 'Alpha Editor', 'vendor' => 'Foo', 'expires_at' => today()->addDays(10)]);
        License::factory()->create(['name' => 'Beta Tool', 'vendor' => 'Bar', 'license_type' => 'perpetual', 'expires_at' => null]);
        License::factory()->create(['name' => 'Gamma Old', 'expires_at' => today()->subDays(5)]);

        $this->actingAs($u)->get('/lizenzen?q=alpha')->assertSee('Alpha Editor')->assertDontSee('Beta Tool');
        $this->actingAs($u)->get('/lizenzen?type=perpetual')->assertSee('Beta Tool')->assertDontSee('Alpha Editor');
        $this->actingAs($u)->get('/lizenzen?status=soon')->assertSee('Alpha Editor')->assertDontSee('Gamma Old');
        $this->actingAs($u)->get('/lizenzen?status=expired')->assertSee('Gamma Old')->assertDontSee('Beta Tool');
        $this->actingAs($u)->get('/lizenzen?status=active')->assertDontSee('Gamma Old');
    }

    public function test_search_input_is_escaped(): void
    {
        $this->actingAs(User::factory()->create())->get('/lizenzen?q=%25')->assertOk();
    }

    public function test_expiry_status(): void
    {
        $this->assertSame('expired', License::factory()->make(['expires_at' => today()->subDay()])->expiryStatus()['key']);
        $this->assertSame('soon', License::factory()->make(['expires_at' => today()->addDays(30)])->expiryStatus()['key']);
        $this->assertSame('ok', License::factory()->make(['expires_at' => today()->addDays(200)])->expiryStatus()['key']);
        $this->assertSame('none', License::factory()->make(['expires_at' => null])->expiryStatus()['key']);
    }

    public function test_dashboard_stats(): void
    {
        License::factory()->create(['purchase_price' => 100, 'expires_at' => today()->addDays(10)]);
        License::factory()->create(['purchase_price' => 50, 'expires_at' => today()->addDays(300)]);
        License::factory()->create(['purchase_price' => 999, 'expires_at' => today()->subDay()]);
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee('150,00 €')->assertSee('Bald fällig');
    }

    public function test_billing_interval_is_stored_validated_and_shown(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/lizenzen', $this->payload(['purchase_price' => '9.99']))->assertRedirect();
        $l = License::firstOrFail();
        $this->assertSame('monthly', $l->billing_interval->value);
        $this->assertSame('9,99 EUR / Monat', $l->priceLabel());
        $this->assertEqualsWithDelta(119.88, $l->yearlyCost(), 0.001);
        $this->actingAs($u)->get("/lizenzen/{$l->id}")->assertSee('9,99 EUR / Monat')->assertSee('119,88');
        $this->actingAs($u)->post('/lizenzen', $this->payload(['billing_interval' => 'weekly']))->assertSessionHasErrors('billing_interval');
        $this->actingAs($u)->get('/lizenzen?interval=yearly')->assertDontSee($l->name);
        $this->actingAs($u)->get('/lizenzen?interval=monthly')->assertSee($l->name);
    }

    public function test_dashboard_converts_monthly_to_yearly_and_ignores_one_time(): void
    {
        License::factory()->create(['purchase_price' => 10, 'billing_interval' => 'monthly', 'expires_at' => null]);
        License::factory()->create(['purchase_price' => 60, 'billing_interval' => 'yearly', 'expires_at' => null]);
        License::factory()->create(['purchase_price' => 500, 'billing_interval' => 'once', 'expires_at' => null]);
        $this->actingAs(User::factory()->create())->get('/')->assertSee('180,00 €')->assertSee('15,00 €');
    }

    public function test_account_access_is_stored_encrypted_and_only_revealed_on_request(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/lizenzen', $this->payload([
            'name' => 'CleanMyMac 5', 'secret' => '', 'account_login' => 'me@example.test',
            'account_password' => 'Konto-Pass-123!', 'account_url' => 'https://example.test/login',
        ]))->assertRedirect();

        $l = License::firstOrFail();
        $raw = DB::table('licenses')->where('id', $l->id)->value('account_secret_ciphertext');
        $this->assertStringNotContainsString('Konto-Pass-123!', $raw);
        $this->assertStringStartsWith('v1:', $raw);
        $this->assertFalse($l->hasSecret());
        $this->assertTrue($l->hasAccount());
        $this->assertArrayNotHasKey('account_secret_ciphertext', $l->toArray());

        foreach (['/lizenzen', "/lizenzen/{$l->id}", "/lizenzen/{$l->id}/edit", '/'] as $uri) {
            $this->actingAs($u)->get($uri)->assertOk()->assertDontSee('Konto-Pass-123!');
        }
        $this->actingAs($u)->get("/lizenzen/{$l->id}")->assertSee('me@example.test');
        $this->actingAs($u)->post("/lizenzen/{$l->id}/konto")->assertOk()->assertJson(['secret' => 'Konto-Pass-123!']);
        $this->app['auth']->guard()->logout();
        $this->post("/lizenzen/{$l->id}/konto")->assertRedirect('/login');

        // leer lassen = unverändert, Häkchen = entfernen
        $this->actingAs($u);
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['name' => 'CleanMyMac 5', 'secret' => '', 'account_password' => '']))->assertRedirect();
        $this->assertSame('Konto-Pass-123!', $l->fresh()->account_password);
        $this->actingAs($u)->put("/lizenzen/{$l->id}", $this->payload(['name' => 'CleanMyMac 5', 'secret' => '', 'clear_account_password' => '1']))->assertRedirect();
        $this->assertFalse($l->fresh()->hasAccountPassword());
    }

    public function test_account_url_must_be_http_or_https(): void
    {
        $u = User::factory()->create();
        foreach (['javascript:alert(1)', 'ftp://example.test', 'data:text/html,x'] as $bad) {
            $this->actingAs($u)->post('/lizenzen', $this->payload(['account_url' => $bad]))->assertSessionHasErrors('account_url');
        }
        $this->assertSame(0, License::count());
    }

    public function test_list_offers_edit_action_and_finds_by_account_login(): void
    {
        $u = User::factory()->create();
        $l = License::factory()->create(['name' => 'Mac Cleaner', 'account_login' => 'findme@example.test']);
        $this->actingAs($u)->get('/lizenzen')->assertSee(route('licenses.edit', $l), false)->assertSee('Bearbeiten');
        $this->actingAs($u)->get('/lizenzen?q=findme')->assertSee('Mac Cleaner');
    }
}
