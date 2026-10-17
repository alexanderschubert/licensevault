<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'Correct-Horse-42';

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@example.test', 'password' => self::PW]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/', '/lizenzen', '/einstellungen/passwort'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertStatus(404);
    }

    public function test_login_with_valid_credentials(): void
    {
        $this->admin();
        $this->post('/login', ['email' => 'admin@example.test', 'password' => self::PW])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->get('/')->assertOk()->assertSee('Willkommen zurück');
    }

    public function test_invalid_credentials_are_rejected_generically(): void
    {
        $this->admin();
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@example.test', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'admin@example.test', 'password' => self::PW])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs($this->admin())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_change_validates_and_updates(): void
    {
        $u = $this->admin();
        $new = 'New-Password-123';
        $this->actingAs($u)->put('/einstellungen/passwort', ['current_password' => 'nope', 'password' => $new, 'password_confirmation' => $new])->assertSessionHasErrors('current_password');
        $this->actingAs($u)->put('/einstellungen/passwort', ['current_password' => self::PW, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->actingAs($u)->put('/einstellungen/passwort', ['current_password' => self::PW, 'password' => $new, 'password_confirmation' => $new])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($new, $u->fresh()->password));
    }

    public function test_admin_can_only_be_created_once(): void
    {
        $this->artisan('licensevault:create-admin', ['--email' => 'a@example.test'])
            ->expectsQuestion('Passwort (mind. 12 Zeichen, Groß-/Kleinbuchstaben, Zahl)', 'Strong-Passw0rd-xyz')
            ->expectsQuestion('Passwort wiederholen', 'Strong-Passw0rd-xyz')
            ->assertSuccessful();
        $this->assertSame(1, User::count());
        $this->artisan('licensevault:create-admin', ['--email' => 'b@example.test'])->assertFailed();
        $this->assertSame(1, User::count());
    }

    public function test_admin_can_be_created_non_interactively_from_an_environment_variable(): void
    {
        putenv('LV_TEST_ADMIN_PW=Strong-Passw0rd-xyz');
        $this->artisan('licensevault:create-admin', ['--email' => 'env@example.test', '--name' => 'Env', '--password-env' => 'LV_TEST_ADMIN_PW'])->assertSuccessful();
        $this->assertTrue(Hash::check('Strong-Passw0rd-xyz', User::where('email', 'env@example.test')->value('password')));

        User::query()->delete();
        putenv('LV_TEST_ADMIN_PW=schwach');
        $this->artisan('licensevault:create-admin', ['--email' => 'env@example.test', '--password-env' => 'LV_TEST_ADMIN_PW'])->assertFailed();
        $this->assertSame(0, User::count());
        putenv('LV_TEST_ADMIN_PW');
    }
}
