<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderGenerator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 08:00:00');
        config(['licensevault.reminder_days' => [30, 7, 1]]);
    }

    private function gen(): int
    {
        return app(ReminderGenerator::class)->run();
    }

    public function test_creates_reminder_for_smallest_crossed_threshold(): void
    {
        $l = License::factory()->create(['expires_at' => today()->addDays(20)]);
        $this->assertSame(1, $this->gen());
        $this->assertSame(30, $l->reminders()->first()->lead_days);

        Carbon::setTestNow('2026-10-27 08:00:00'); // 10 Tage übrig -> weiterhin Stufe 30
        $this->assertSame(0, $this->gen());
        Carbon::setTestNow('2026-11-02 08:00:00'); // 4 Tage übrig -> Stufe 7
        $this->assertSame(1, $this->gen());
        Carbon::setTestNow('2026-11-06 08:00:00'); // 0 Tage -> Stufe 0
        $this->assertSame(1, $this->gen());
        $this->assertSame([0, 7, 30], $l->reminders()->orderBy('lead_days')->pluck('lead_days')->all());
    }

    public function test_is_idempotent_and_respects_dismissal(): void
    {
        $l = License::factory()->create(['expires_at' => today()->addDays(5)]);
        $this->gen();
        $r = $l->reminders()->firstOrFail();
        $r->update(['dismissed_at' => now()]);
        $this->assertSame(0, $this->gen());
        $this->assertSame(1, Reminder::count());
        $this->assertNotNull($r->fresh()->dismissed_at);
    }

    public function test_ignores_far_future_perpetual_and_long_expired(): void
    {
        License::factory()->create(['expires_at' => today()->addDays(200)]);
        License::factory()->create(['expires_at' => null]);
        License::factory()->create(['expires_at' => today()->subDays(90)]);
        $this->assertSame(0, $this->gen());
    }

    public function test_recently_expired_and_renewal_dates_create_reminders(): void
    {
        $a = License::factory()->create(['expires_at' => today()->subDays(3)]);
        $b = License::factory()->create(['expires_at' => null, 'renewal_date' => today()->addDays(6)]);
        $this->assertSame(2, $this->gen());
        $this->assertSame(0, $a->reminders()->first()->lead_days);
        $this->assertSame('renewal', $b->reminders()->first()->kind);
        $this->assertStringContainsString('abgelaufen', $a->reminders()->first()->message());
    }

    public function test_stale_open_reminders_are_removed_when_date_changes(): void
    {
        $l = License::factory()->create(['expires_at' => today()->addDays(5)]);
        $this->gen();
        $l->update(['expires_at' => today()->addDays(300)]); // verlängert
        $this->gen();
        $this->assertSame(0, Reminder::count());
    }

    public function test_pages_actions_and_badge(): void
    {
        $u = User::factory()->create();
        $l = License::factory()->create(['name' => 'Ablauf-Lizenz', 'expires_at' => today()->addDays(3)]);
        $this->actingAs($u)->post('/erinnerungen/pruefen')->assertRedirect();
        $this->assertSame(1, Reminder::open()->count());
        $this->actingAs($u)->get('/erinnerungen')->assertOk()->assertSee('Ablauf-Lizenz')->assertSee('in 3 Tagen');
        $this->actingAs($u)->get('/')->assertOk()->assertSee('1 offen');
        $this->actingAs($u)->post('/erinnerungen/'.Reminder::first()->id.'/erledigt')->assertRedirect();
        $this->assertSame(0, Reminder::open()->count());
    }

    public function test_guests_are_blocked_and_command_is_scheduled(): void
    {
        $this->get('/erinnerungen')->assertRedirect('/login');
        $this->post('/erinnerungen/pruefen')->assertRedirect('/login');
        $events = collect(app(Schedule::class)->events())->map->command->implode(' ');
        $this->assertStringContainsString('licensevault:generate-reminders', $events);
        $this->artisan('licensevault:generate-reminders')->assertSuccessful();
    }

    public function test_dashboard_lists_the_same_due_items_as_the_reminders(): void
    {
        $u = User::factory()->create();
        License::factory()->create(['name' => 'Nur-Ablauf', 'expires_at' => today()->addDays(10)]);
        License::factory()->create(['name' => 'Nur-Verlaengerung', 'expires_at' => null, 'renewal_date' => today()->addDays(5)]);
        License::factory()->create(['name' => 'Kuerzlich-Abgelaufen', 'expires_at' => today()->subDays(3)]);
        License::factory()->create(['name' => 'Weit-Weg', 'expires_at' => today()->addDays(200)]);
        License::factory()->create(['name' => 'Ewig', 'expires_at' => null]);
        $this->gen();

        $html = $this->actingAs($u)->get('/')->assertOk()->getContent();
        $card = substr($html, strpos($html, 'Bald fällig'), strpos($html, 'Zuletzt geändert') - strpos($html, 'Bald fällig'));

        foreach (['Nur-Ablauf', 'Nur-Verlaengerung', 'Verlängerung in 5 Tagen', 'Kuerzlich-Abgelaufen', 'abgelaufen seit 3 Tagen'] as $expected) {
            $this->assertStringContainsString($expected, $card);
        }
        $this->assertStringNotContainsString('Weit-Weg', $card);
        $this->assertStringNotContainsString('Ewig', $card);

        // jede offene Erinnerung ist auch in der Dashboard-Karte sichtbar
        foreach (Reminder::open()->with('license')->get() as $r) {
            $this->assertStringContainsString($r->license->name, $card);
        }
    }

    public function test_status_filter_soon_includes_renewals(): void
    {
        $u = User::factory()->create();
        License::factory()->create(['name' => 'Renew-Soon', 'expires_at' => null, 'renewal_date' => today()->addDays(4)]);
        License::factory()->create(['name' => 'Far-Away', 'expires_at' => today()->addDays(300)]);
        $this->actingAs($u)->get('/lizenzen?status=soon')->assertSee('Renew-Soon')->assertDontSee('Far-Away');
    }
}
