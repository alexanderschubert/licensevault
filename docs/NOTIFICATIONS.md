# Benachrichtigungen (Web-Push und Gotify)

LicenseVault prüft täglich um **06:00 Uhr** Ablauf- und Verlängerungsdaten und schickt für jede **neu erreichte Frist** (`REMINDER_DAYS`, Standard 30, 7 und 1 Tag vorher sowie am Fälligkeitstag) **eine** Nachricht: Name der Lizenz und Datum – nie Schlüssel oder Passwörter. Bis zu drei neue Erinnerungen kommen einzeln, mehr werden zusammengefasst. Beide Kanäle sind optional und können gleichzeitig laufen. Unter **Einstellungen → Benachrichtigungen** siehst du den Status und kannst eine Testnachricht senden.

## Web-Push (iPhone, Android, Desktop)

Nachrichten direkt aufs Gerät, ohne zusätzliche App. Voraussetzungen: HTTPS-Adresse (siehe `PWA.md`) und auf dem iPhone **iOS 16.4 oder neuer** mit der **zum Home-Bildschirm hinzugefügten** App.

0. **Zuerst die neue Version bauen und starten** (der Befehl unten existiert erst danach; für die neue Tabelle `RUN_MIGRATIONS=true` setzen, danach wieder `false`):

   ```bash
   docker compose -f compose.yaml up -d --build
   ```

1. VAPID-Schlüssel erzeugen:

   ```bash
   docker compose -f compose.yaml exec -u app app php artisan licensevault:vapid-keys
   ```

2. Die ausgegebenen Zeilen in die `.env` eintragen (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT=mailto:du@example.com`). Den privaten Schlüssel geheim halten.
3. Container neu erzeugen: `docker compose -f compose.yaml up -d --force-recreate`.
4. Am iPhone die App **vom Home-Bildschirm** öffnen → *Einstellungen → Benachrichtigungen* → **Auf diesem Gerät aktivieren** und die iOS-Abfrage bestätigen.
5. **Testnachricht senden** drücken.

Hinweise:
- Der Server schickt die verschlüsselte Nachricht an den Push-Dienst von Apple/Google; der Container braucht dafür Internetzugang nach außen.
- Wechselst du die VAPID-Schlüssel, müssen alle Geräte neu aktivieren.
- Blockiert iOS die Mitteilungen: *Einstellungen → Mitteilungen → LicenseVault* erlauben.
- Es gibt keinen Offline-Cache: Der Service Worker (`/sw.js`) zeigt nur Benachrichtigungen an und speichert keine Seiten.

## Gotify (selbst gehosteter Push-Server)

1. In Gotify unter **Apps** eine App „LicenseVault“ anlegen und das Token kopieren.
2. In der `.env`:

   ```dotenv
   GOTIFY_URL=https://gotify.deine-domain
   GOTIFY_TOKEN=<App-Token>
   GOTIFY_PRIORITY=5
   ```

3. Container neu erzeugen und unter *Einstellungen → Benachrichtigungen* eine Testnachricht senden (oder `docker compose exec -u app app php artisan licensevault:notify-test`).

Das Token wird als Header (`X-Gotify-Key`) gesendet, nie in der URL oder im Log. Ist `APP_URL` gesetzt, öffnet ein Tipp auf die Nachricht direkt die passende Lizenz. Für Android gibt es die offizielle Gotify-App; für das iPhone existieren nur inoffizielle Clients – dort ist Web-Push die bessere Wahl.
