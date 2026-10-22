<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\Channels\GotifyChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Notifier;
use App\Notifications\PushMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeChannel;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 08:00:00');
        config([
            'licensevault.reminder_days' => [30, 7, 1],
            'app.url' => 'https://vault.example.test',
            'licensevault.gotify.url' => null, 'licensevault.gotify.token' => null,
            'licensevault.push.public_key' => null, 'licensevault.push.private_key' => null,
        ]);
    }

    private function gotify(): void
    {
        config(['licensevault.gotify.url' => 'https://gotify.example.test/', 'licensevault.gotify.token' => 'APP-TOKEN-123', 'licensevault.gotify.priority' => 6]);
    }

    private function sub(string $suffix = 'a'): array
    {
        return ['endpoint' => "https://web.push.apple.com/{$suffix}", 'keys' => ['p256dh' => 'BPubKey'.$suffix, 'auth' => 'AuthKey'.$suffix]];
    }

    public function test_pages_and_endpoints_require_login(): void
    {
        $this->get('/einstellungen/benachrichtigungen')->assertRedirect('/login');
        $this->postJson('/einstellungen/push', $this->sub())->assertUnauthorized();
        $this->post('/einstellungen/push/abmelden', ['endpoint' => 'https://x.test'])->assertRedirect('/login');
        $this->post('/einstellungen/benachrichtigungen/test')->assertRedirect('/login');
    }

    public function test_settings_page_shows_setup_help_or_status_without_leaking_secrets(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/einstellungen/benachrichtigungen')->assertOk()
            ->assertSee('nicht eingerichtet')->assertSee('licensevault:vapid-keys')->assertSee('GOTIFY_TOKEN');

        $this->gotify();
        config(['licensevault.push.public_key' => 'PUBLIC-KEY-XYZ', 'licensevault.push.private_key' => 'PRIVATE-KEY-SECRET']);
        $this->actingAs($u)->get('/einstellungen/benachrichtigungen')->assertOk()
            ->assertSee('gotify.example.test')->assertSee('eingerichtet')
            ->assertDontSee('APP-TOKEN-123')->assertDontSee('PRIVATE-KEY-SECRET');
    }

    public function test_subscription_is_validated_deduplicated_and_labelled(): void
    {
        $u = User::factory()->create();
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1';

        $this->actingAs($u)->withHeader('User-Agent', $ua)->postJson('/einstellungen/push', $this->sub())->assertCreated();
        $this->actingAs($u)->postJson('/einstellungen/push', $this->sub())->assertCreated(); // gleicher Endpoint -> kein Duplikat
        $this->assertSame(1, PushSubscription::count());
        $this->assertSame('iPhone (Safari)', PushSubscription::first()->label);
        $this->assertArrayNotHasKey('endpoint', PushSubscription::first()->toArray());

        $this->actingAs($u)->postJson('/einstellungen/push', array_merge($this->sub('b'), ['endpoint' => 'http://insecure.test/x']))->assertUnprocessable();
        $this->actingAs($u)->postJson('/einstellungen/push', ['endpoint' => 'https://x.test/y'])->assertUnprocessable();
        $this->assertSame(1, PushSubscription::count());
    }

    public function test_device_can_unsubscribe_itself_or_be_removed(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/einstellungen/push', $this->sub('a'));
        $this->actingAs($u)->postJson('/einstellungen/push', $this->sub('b'));
        $this->actingAs($u)->postJson('/einstellungen/push/abmelden', ['endpoint' => $this->sub('a')['endpoint']])->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->actingAs($u)->delete('/einstellungen/push/'.PushSubscription::first()->id)->assertRedirect();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_gotify_sends_with_token_in_header_and_click_url(): void
    {
        Http::fake(['gotify.example.test/*' => Http::response(['id' => 1], 200)]);
        $this->gotify();

        $n = (new GotifyChannel)->send(new PushMessage('Adobe', 'Lizenz läuft ab in 7 Tagen', '/lizenzen/5', 'r-5'));

        $this->assertSame(1, $n);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://gotify.example.test/message'
                && $request->hasHeader('X-Gotify-Key', 'APP-TOKEN-123')
                && ! str_contains($request->url(), 'APP-TOKEN')
                && $request['title'] === 'Adobe' && $request['priority'] === 6
                && $request['extras']['client::notification']['click']['url'] === 'https://vault.example.test/lizenzen/5';
        });
    }

    public function test_gotify_failures_return_zero_and_do_not_throw(): void
    {
        $this->gotify();
        Http::fake(['*' => Http::response('nope', 401)]);
        $this->assertSame(0, (new GotifyChannel)->send(new PushMessage('t', 'b')));
        Http::fake(fn () => throw new ConnectionException('down'));
        $this->assertSame(0, (new GotifyChannel)->send(new PushMessage('t', 'b')));
        config(['licensevault.gotify.token' => null]);
        Http::fake();
        $this->assertSame(0, (new GotifyChannel)->send(new PushMessage('t', 'b')));
        Http::assertNothingSent();
    }

    public function test_web_push_is_skipped_without_keys_or_subscribers(): void
    {
        $this->assertFalse((new WebPushChannel)->configured());
        $this->assertSame(0, (new WebPushChannel)->send(new PushMessage('t', 'b')));
        config(['licensevault.push.public_key' => 'pub', 'licensevault.push.private_key' => 'priv']);
        $this->assertTrue((new WebPushChannel)->configured());
        $this->assertSame(0, (new WebPushChannel)->send(new PushMessage('t', 'b'))); // keine Geräte
    }

    public function test_new_reminders_are_pushed_once_each_and_summarised_when_many(): void
    {
        $fake = new FakeChannel;
        $this->app->instance(Notifier::class, new Notifier([$fake]));

        License::factory()->create(['name' => 'Alpha', 'expires_at' => today()->addDays(6)]);
        $this->artisan('licensevault:generate-reminders')->assertSuccessful();
        $this->assertCount(1, $fake->sent);
        $this->assertSame('Alpha', $fake->sent[0]->title);
        $this->assertStringContainsString('in 6 Tagen', $fake->sent[0]->body);
        $this->assertStringStartsWith('/lizenzen/', $fake->sent[0]->path);

        $this->artisan('licensevault:generate-reminders')->assertSuccessful(); // nichts Neues -> keine Nachricht
        $this->assertCount(1, $fake->sent);

        foreach (range(1, 4) as $i) {
            License::factory()->create(['name' => "Mehr {$i}", 'expires_at' => today()->addDays(5)]);
        }
        $this->artisan('licensevault:generate-reminders')->assertSuccessful();
        $this->assertCount(2, $fake->sent); // eine Sammelnachricht für 4 neue
        $this->assertStringContainsString('4 neue Erinnerungen', $fake->sent[1]->title);
        $this->assertSame('/erinnerungen', $fake->sent[1]->path);
    }

    public function test_unconfigured_channels_send_nothing_and_never_leak_secrets(): void
    {
        $fake = new FakeChannel(false);
        $notifier = new Notifier([$fake]);
        $l = License::factory()->withKey('GEHEIMER-SCHLUESSEL')->create(['expires_at' => today()->addDays(3)]);
        $this->assertSame(0, $notifier->notifyReminders(collect([Reminder::create(['license_id' => $l->id, 'kind' => 'expires', 'due_on' => today()->addDays(3), 'lead_days' => 7])])));
        $this->assertSame([], $fake->sent);

        $ok = new FakeChannel;
        (new Notifier([$ok]))->notifyReminders(collect([Reminder::first()]));
        $this->assertStringNotContainsString('GEHEIMER', json_encode($ok->sent[0]));
    }

    public function test_reminder_check_button_and_test_button_use_the_notifier(): void
    {
        $fake = new FakeChannel;
        $this->app->instance(Notifier::class, new Notifier([$fake]));
        $u = User::factory()->create();
        License::factory()->create(['name' => 'Button-Lizenz', 'expires_at' => today()->addDays(2)]);

        $this->actingAs($u)->post('/erinnerungen/pruefen')->assertRedirect();
        $this->assertCount(1, $fake->sent);
        $this->assertSame('Button-Lizenz', $fake->sent[0]->title);

        $this->actingAs($u)->post('/einstellungen/benachrichtigungen/test')->assertSessionHas('status');
        $this->assertCount(2, $fake->sent);

        $this->app->instance(Notifier::class, new Notifier([new FakeChannel(false)]));
        $this->actingAs($u)->post('/einstellungen/benachrichtigungen/test')->assertSessionHasErrors('notify');
    }

    public function test_vapid_command_and_service_worker(): void
    {
        $this->artisan('licensevault:vapid-keys')->expectsOutputToContain('VAPID_PUBLIC_KEY=')->expectsOutputToContain('VAPID_PRIVATE_KEY=')->assertSuccessful();

        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("addEventListener('push'", $sw);
        $this->assertStringContainsString("addEventListener('notificationclick'", $sw);
        $this->assertStringNotContainsString("addEventListener('fetch'", $sw); // kein Caching/Abfangen von Anfragen
        $this->assertStringNotContainsString('caches.', $sw);
    }

    public function test_notify_test_command_requires_a_channel(): void
    {
        $this->app->instance(Notifier::class, new Notifier([new FakeChannel(false)]));
        $this->artisan('licensevault:notify-test')->assertFailed();
        $this->app->instance(Notifier::class, new Notifier([new FakeChannel]));
        $this->artisan('licensevault:notify-test')->assertSuccessful();
    }
}
