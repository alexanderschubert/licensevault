# LicenseVault – Projektleitfaden

Private, selbst gehostete Single-User-App (Laravel 13 / PHP 8.5, Blade + Tailwind 4 + Alpine, PostgreSQL) für
Software-Lizenzen, Konto-Zugänge, Dokumente und HomeKit-/Matter-QR-Codes. Sprache der Oberfläche: Deutsch.
Vollständiger Plan und Status: `README.md`. Anleitungen: `docs/`.

## Aufbau

- `app/` Controller, Modelle, Enums, Services (`ReminderGenerator`), `Backup/` (verschlüsselte Backups), `Notifications/` (Web-Push, Gotify), `Support/SecretVault` + `Matter`
- `resources/views` Blade (Komponenten in `components/`), `resources/js/app.js` (Alpine: Theme, Scanner, Push), `resources/css/app.css` (Design-Tokens, Glas-Look)
- `docker/` + `Dockerfile` (Ziele `app`, `web`, `aio`), `compose*.yaml`, `unraid/` (Templates), `.github/workflows` (CI, Release)
- `tests/Feature`, `tests/Unit` – PHPUnit mit SQLite; echte PostgreSQL-/Browser-Checks nur im Container

## Regeln

- **Geheimnisse** (Lizenzschlüssel, Konto-Passwörter, Codes, Payloads) nur über `SecretVault` verschlüsselt speichern, nie in Listen/HTML/CSV/Logs; Anzeige nur per Klick über POST-Routen; Seiten mit Kopplungsdaten mit `NoStore`-Middleware.
- Eine Phase/Änderung = Tests dazu; vor dem Commit `vendor/bin/pint` und `php artisan test`. Conventional Commits.
- Neue Migrationen nur vorwärts; Updates nach Backup. Keine `latest`-Images.
- **`.env` im Projektordner nie anlegen, überschreiben oder löschen**: Der lokale Ordner ist zugleich das Unraid-Share des Nutzers (dort liegt seine echte `.env`). Testläufe in einer Kopie außerhalb des Projekts machen.

## Entwickeln ohne lokales PHP/Node

Es gibt kein PHP/Composer/Node auf dem Mac; alles läuft in Docker (`composer:2.10`, `node:22-alpine`) auf einer Kopie des Projekts
(ohne `vendor`, `node_modules`, `data`, `.env`):

```bash
docker run --rm -v <kopie>:/app -w /app composer:2.10 php artisan test
docker run --rm -v <kopie>:/app -w /app node:22-alpine npm run build   # Tests brauchen public/build
```

Vollständiger Stack zum Ausprobieren: Kopie mit eigener `.env` (Testwerte) und `docker compose -f compose.yaml -f compose.local-db.yaml up -d --build`; All-in-One: `docker build --target aio -t licensevault:test .`.

## Deployment

- Nutzer betreibt es auf Unraid (Share = Projektordner), Zugriff über Nginx Proxy Manager mit HTTPS (nötig für Kamera/PWA/Push).
- Release: Git-Tag `vX.Y.Z` baut GHCR-Images (`licensevault-app`, `licensevault-web`, `licensevault` = All-in-One). Repo ist **privat**.
