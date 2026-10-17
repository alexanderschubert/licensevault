<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_forwarded_proto_from_trusted_proxy_produces_https_urls(): void
    {
        $r = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'vault.example.test', 'X-Forwarded-Port' => '443'])
            ->get('/login')->assertOk();

        $r->assertSee('action="https://vault.example.test/login"', false);
        $r->assertSee('href="https://vault.example.test/build/assets/', false);
        $this->assertStringNotContainsString('http://', preg_replace('#http://www\.w3\.org[^"\']*#', '', $r->getContent()));
    }

    public function test_https_app_url_forces_https_without_forwarded_headers(): void
    {
        config(['app.url' => 'https://vault.example.test']);
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $this->get('/login')->assertOk()->assertSee('href="https://', false);
    }

    public function test_untrusted_remote_cannot_spoof_forwarded_headers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.test'])
            ->get('/login')->assertOk()->assertDontSee('evil.example.test', false);
    }
}
