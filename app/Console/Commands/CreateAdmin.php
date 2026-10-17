<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'licensevault:create-admin {--name=Admin} {--email=} {--password-env= : Name einer Umgebungsvariablen mit dem Passwort (nicht interaktiv)}';

    protected $description = 'Legt das einzige Administratorkonto an (Passwort wird verdeckt abgefragt).';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('Es existiert bereits ein Konto. LicenseVault ist eine Einzelnutzer-Instanz.');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: $this->ask('E-Mail');
        if ($envName = $this->option('password-env')) {
            $password = (string) getenv($envName);
        } else {
            $password = $this->secret('Passwort (mind. 12 Zeichen, Groß-/Kleinbuchstaben, Zahl)');

            if ($password !== $this->secret('Passwort wiederholen')) {
                $this->error('Die Passwörter stimmen nicht überein.');

                return self::FAILURE;
            }
        }

        $v = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => [Password::min(12)->mixedCase()->numbers()]]
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
        $this->info('Administratorkonto wurde angelegt.');

        return self::SUCCESS;
    }
}
