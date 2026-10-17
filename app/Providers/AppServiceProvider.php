<?php

namespace App\Providers;

use App\Backup\DatabaseDumper;
use App\Backup\PostgresDumper;
use App\Models\HomekitDevice;
use App\Models\License;
use App\Models\Reminder;
use App\Notifications\Channels\GotifyChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Notifier;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseDumper::class, PostgresDumper::class);
        $this->app->singleton(Notifier::class, fn ($app) => new Notifier([$app->make(WebPushChannel::class), $app->make(GotifyChannel::class)]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ist APP_URL eine https-Adresse, werden alle erzeugten Links/Assets ebenfalls https (auch ohne X-Forwarded-Proto).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Nur diese Typen dürfen Dokumente tragen (kurze Namen statt Klassenpfade in der DB).
        Relation::enforceMorphMap(['license' => License::class, 'homekit' => HomekitDevice::class]);

        View::composer('layouts.app', fn ($view) => $view->with('openReminders', Reminder::open()->count()));
    }
}
