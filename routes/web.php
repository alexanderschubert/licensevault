<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HomekitController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\ReminderController;
use App\Http\Middleware\NoStore;
use App\Models\Document;
use App\Models\HomekitDevice;
use App\Models\License;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Keine öffentliche Registrierung – Konto nur über `licensevault:create-admin`.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', function () {
        try {
            DB::connection()->getPdo();
            $db = true;
        } catch (Throwable) {
            $db = false;
        }

        return view('dashboard', [
            'dbOk' => $db,
            'activeCount' => License::active()->count(),
            'yearlyCost' => License::active()->where('currency', 'EUR')->whereIn('billing_interval', ['monthly', 'yearly'])->get()->sum(fn (License $l) => $l->yearlyCost()),
            'homekitCount' => HomekitDevice::count(),
            'dueItems' => $due = License::dueItems(),
            'soonCount' => $due->where('days', '>=', 0)->count(),
            'overdueCount' => $due->where('days', '<', 0)->count(),
            'recent' => collect()
                ->merge(License::latest('updated_at')->limit(5)->get()->map(fn ($m) => ['Lizenz', $m->name, route('licenses.show', $m), $m->updated_at]))
                ->merge(HomekitDevice::latest('updated_at')->limit(5)->get()->map(fn ($m) => ['HomeKit', $m->name, route('homekit.show', $m), $m->updated_at]))
                ->merge(Document::latest('updated_at')->limit(5)->get()->map(fn ($m) => ['Dokument', $m->title, route('documents.index'), $m->updated_at]))
                ->sortByDesc(fn ($r) => $r[3])->take(6)->values(),
        ]);
    })->name('dashboard');

    Route::get('/einstellungen/passwort', [PasswordController::class, 'edit'])->name('settings.password');
    Route::get('/einstellungen/benachrichtigungen', [NotificationSettingsController::class, 'index'])->name('settings.notifications');
    Route::post('/einstellungen/benachrichtigungen/test', [NotificationSettingsController::class, 'test'])->middleware('throttle:6,1')->name('settings.notifications.test');
    Route::post('/einstellungen/push', [NotificationSettingsController::class, 'subscribe'])->middleware('throttle:20,1')->name('push.subscribe');
    Route::post('/einstellungen/push/abmelden', [NotificationSettingsController::class, 'unsubscribeByEndpoint'])->middleware('throttle:20,1')->name('push.unsubscribe-endpoint');
    Route::delete('/einstellungen/push/{subscription}', [NotificationSettingsController::class, 'unsubscribe'])->name('push.destroy');
    Route::put('/einstellungen/passwort', [PasswordController::class, 'update'])->name('settings.password.update');

    Route::resource('lizenzen', LicenseController::class)->names('licenses')->parameters(['lizenzen' => 'license'])->except([]);
    Route::post('/lizenzen/{license}/schluessel/{key}', [LicenseController::class, 'reveal'])->middleware('throttle:30,1')->name('licenses.reveal');
    Route::post('/lizenzen/{license}/konto', [LicenseController::class, 'revealAccount'])->middleware('throttle:30,1')->name('licenses.reveal-account');
    Route::post('/lizenzen/{license}/wiederherstellung', [LicenseController::class, 'revealRecovery'])->middleware('throttle:30,1')->name('licenses.reveal-recovery');
    Route::get('/lizenzen/{license}/icon', [LicenseController::class, 'icon'])->name('licenses.icon');

    // HomeKit: Kopplungsdaten dürfen nie gecacht werden
    Route::middleware(NoStore::class)->group(function () {
        Route::get('/homekit/druck', [HomekitController::class, 'print'])->name('homekit.print');
        Route::get('/homekit/druck.pdf', [HomekitController::class, 'printPdf'])->name('homekit.print.pdf');
        Route::get('/homekit/{device}/pdf', [HomekitController::class, 'devicePdf'])->name('homekit.pdf');
        Route::resource('homekit', HomekitController::class)->parameters(['homekit' => 'device'])->names('homekit');
        Route::get('/homekit/{device}/foto', [HomekitController::class, 'photo'])->name('homekit.photo');
        Route::get('/homekit/{device}/qr', [HomekitController::class, 'qr'])->name('homekit.qr');
        Route::post('/homekit/{device}/code', [HomekitController::class, 'revealCode'])->middleware('throttle:30,1')->name('homekit.code');
    });

    Route::middleware(NoStore::class)->group(function () {
        Route::get('/dokumente', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/dokumente', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/dokumente/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('/dokumente/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    Route::get('/erinnerungen', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('/erinnerungen/pruefen', [ReminderController::class, 'check'])->name('reminders.check');
    Route::post('/erinnerungen/erledigt', [ReminderController::class, 'dismissAll'])->name('reminders.dismiss-all');
    Route::post('/erinnerungen/{reminder}/erledigt', [ReminderController::class, 'dismiss'])->name('reminders.dismiss');

    Route::get('/export/lizenzen.csv', [ExportController::class, 'licenses'])->name('export.licenses');
    Route::get('/export/homekit.csv', [ExportController::class, 'homekit'])->name('export.homekit');

    Route::middleware(NoStore::class)->prefix('backup')->name('backup.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'create'])->middleware('throttle:6,1')->name('create');
        Route::post('/{name}/pruefen', [BackupController::class, 'verify'])->middleware('throttle:6,1')->name('verify');
        Route::get('/{name}/download', [BackupController::class, 'download'])->name('download');
        Route::delete('/{name}', [BackupController::class, 'destroy'])->name('destroy');
    });

    // Platzhalter bis zu den jeweiligen Phasen
    foreach ([
    ] as $name => [$uri, $title, $phase]) {
        Route::view($uri, 'placeholder', ['title' => $title, 'phase' => $phase])->name($name);
    }
});
