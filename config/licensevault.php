<?php

return [
    'version' => '0.7.0',

    // Vorlaufzeiten (Tage vor Ablauf/Verlängerung), kommagetrennt, z. B. "30,7,1"
    'reminder_days' => array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) env('REMINDER_DAYS', '30,7,1'))),
        fn ($d) => $d > 0
    ))),

    // Wie lange nach dem Ablauf noch erinnert wird
    'reminder_overdue_days' => 30,

    'document_max_kb' => 10240,
    'document_mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'heic', 'heif', 'txt'],

    'backup' => [
        // Pflicht für Sicherungen (mind. 12 Zeichen); getrennt von APP_KEY und DB-Passwort aufbewahren.
        'passphrase' => env('BACKUP_PASSPHRASE'),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'time' => env('BACKUP_TIME', '03:00'),
    ],

    // Web-Push (VAPID): Schlüssel mit `php artisan licensevault:vapid-keys` erzeugen
    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.invalid'),
    ],

    // Gotify (selbst gehosteter Push-Server): URL des Servers und App-Token
    'gotify' => [
        'url' => env('GOTIFY_URL'),
        'token' => env('GOTIFY_TOKEN'),
        'priority' => (int) env('GOTIFY_PRIORITY', 5),
    ],
];
