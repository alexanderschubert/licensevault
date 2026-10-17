# LicenseVault

Private, selbst gehostete Single-User-Web-App zur Verwaltung von Software-Lizenzen, Produktschlüsseln, Abonnements, Dokumenten und HomeKit-QR-Codes. Entwickelt für Docker Compose und Unraid.

## Projektziele

- Ein persönliches Benutzerkonto, keine Mehrmandanten- oder Teamverwaltung.
- Moderne Desktop-Oberfläche mit responsivem Layout sowie Light-/Dark-Mode.
- Software-Lizenzen mit Hersteller, Produkt, Lizenztyp, Kaufpreis, Kaufdatum, Ablaufdatum, Gerätelimit, Notizen und Anhängen.
- HomeKit-Geräte mit Raum, Hersteller, Modell, Status, QR-Code, Einrichtungscode und Notizen.
- Geschützte Dokumente wie Rechnungen, Belege und Screenshots.
- Volltextsuche bzw. Filter nach Kategorien, Tags, Hersteller und Status.
- Erinnerungen für Ablaufdaten und Verlängerungen.
- Export, Backup und getestete Wiederherstellung.
- PostgreSQL wahlweise als eigener Compose-Service oder als vorhandene externe Instanz.
- Keine Secrets in Git, Logs, Fehlermeldungen oder Beispielkonfigurationen.

## Technischer Stack

- Laravel 13 / PHP 8.5
- Blade, Tailwind CSS, Alpine.js und Vite
- PostgreSQL 16+
- Nginx als Webserver
- Docker Compose
- Laravel Scheduler für geplante Erinnerungen
- Optionaler Queue-Worker erst dann, wenn asynchrone Aufgaben benötigt werden
- GitHub und GitHub Actions für Versionsverwaltung und CI

Vor dem Start der Implementierung müssen die konkret verwendeten Image-Tags, PHP-Erweiterungen und Laravel-Kompatibilität in CI geprüft und auf reproduzierbare Versionen festgelegt werden. Keine ungetaggten `latest`-Images für den produktiven Betrieb.

## Architektur

```text
Browser
  |
  v
Nginx (Container)
  |
  v
Laravel / PHP-FPM (Container)
  |                 \
  v                  v
PostgreSQL        Persistente Dateien
(intern oder      (Dokumente, QR-Code-
 extern)           Bilder/Exports)
  |
  v
Backup-Ziel (separat konfiguriert)
```

Die Anwendung verwendet ausschließlich `DB_*`-Umgebungsvariablen. Ob PostgreSQL intern oder extern läuft, ist eine Deployment-Entscheidung und ändert weder Models noch Migrations noch Anwendungscode.

## Datenbankmodi

### Modus A: Eigener PostgreSQL-Container

Die Compose-Datei startet einen PostgreSQL-Service mit eigenem persistentem Volume. Laravel verbindet sich intern über den Compose-Servicenamen, z. B. `db`, und den Container-Port `5432`.

Beispiel für `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=licensevault
DB_USERNAME=licensevault
DB_PASSWORD=CHANGE_ME_TO_A_LONG_RANDOM_SECRET
DB_SSLMODE=prefer
```

Das Passwort ist ein Platzhalter und muss durch ein langes, zufälliges Secret ersetzt werden. Die Datenbank darf nicht ohne ausdrücklichen Bedarf auf dem Host veröffentlicht werden. Ein Healthcheck stellt sicher, dass PostgreSQL bereit ist, bevor Migrationen ausgeführt werden.

### Modus B: Bestehendes PostgreSQL

Die Compose-Datei startet keinen PostgreSQL-Service. Laravel verbindet sich mit der bereits laufenden Instanz. Für LicenseVault wird eine eigene Datenbank und ein eigener Datenbankbenutzer angelegt; keine gemeinsame Datenbank oder Superuser-Zugangsdaten verwenden.

Beispiel für `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=192.168.1.10
DB_PORT=5432
DB_DATABASE=licensevault
DB_USERNAME=licensevault_app
DB_PASSWORD=CHANGE_ME_TO_A_LONG_RANDOM_SECRET
DB_SSLMODE=prefer
```

`192.168.1.10` ist nur ein Beispiel und muss durch die tatsächliche, vom App-Container erreichbare Adresse ersetzt werden. `DB_SSLMODE` muss an die Konfiguration des PostgreSQL-Servers angepasst werden. Für Verbindungen über unsichere Netzwerke ist TLS mit Zertifikatsprüfung vorzusehen.

### Umschalten

- `compose.yaml` enthält die App-Services.
- `compose.local-db.yaml` ergänzt den internen `db`-Service.
- Interne Datenbank starten: `docker compose -f compose.yaml -f compose.local-db.yaml up -d`
- Externe Datenbank verwenden: `docker compose -f compose.yaml up -d`
- Vor einem Wechsel Backup erstellen und prüfen, ob die Zielinstanz Schema und Daten enthält.
- Der Modus wird dokumentiert; nicht benötigte DB-Services dürfen nicht gleichzeitig gestartet werden.
- Datenmigration zwischen DB-Instanzen erfolgt explizit über Dump/Restore, niemals automatisch beim Start.

## Unraid-Verzeichnisse und Persistenz

Empfohlene Host-Verzeichnisse:

```text
/mnt/user/appdata/licensevault/
├── storage/             # Laravel storage, Logs und temporäre Dateien
├── uploads/             # Geschützte Dokumente und Anhänge
├── exports/             # Erzeugte Exporte
├── postgres/            # Nur für internen PostgreSQL-Modus
└── backups/             # Lokale Backup-Zwischenablage, nicht einzige Kopie
```

Container-Pfade und UID/GID müssen im Dockerfile bzw. Compose konsistent definiert werden. `uploads/` darf nicht direkt als öffentliches Nginx-Verzeichnis erreichbar sein. Dateien werden über autorisierte Laravel-Routen ausgeliefert.

Backups sollten zusätzlich auf ein anderes Medium oder ein anderes System kopiert werden. Ein Backup auf derselben Festplatte schützt nicht vor deren Ausfall.

## Sicherheit

- Ein einzelnes Administratorkonto; öffentliche Registrierung deaktiviert.
- Sichere Passwort-Hashes über Laravel.
- Login-Rate-Limits, CSRF-Schutz, sichere Session-Cookies und automatische Session-Erneuerung.
- Secrets ausschließlich in `.env` oder einer geschützten Secret-Verwaltung; `.env` niemals committen.
- Lizenzschlüssel und HomeKit-Einrichtungscodes als Geheimnisse behandeln. Vor Speicherung serverseitig mit einem versionierten Verschlüsselungsdienst auf Basis von Laravel Encryption oder einer dedizierten Anwendungsschlüsselstrategie verschlüsseln.
- Verschlüsselungsschlüssel separat sichern und getrennt von Datenbank-Backups aufbewahren. Ohne Schlüssel sind verschlüsselte Daten nicht wiederherstellbar.
- Schlüssel nie in Logs, URLs, Analytics oder Fehlermeldungen ausgeben.
- QR-Codes mit HomeKit-Kopplungsdaten nur nach Authentifizierung anzeigen; Cache-Header auf private/no-store setzen.
- Uploads auf erlaubte Dateitypen und Dateigröße begrenzen; Originaldateien nicht direkt ausführbar machen.
- Nginx Proxy Manager kann als Reverse Proxy dienen. App nicht ungeschützt öffentlich freigeben; bei externem Zugriff HTTPS und zusätzliche Zugriffsbeschränkung erwägen.
- Regelmäßige Updates, minimale Container-Rechte und keine unnötigen veröffentlichten Ports.
- Backups verschlüsseln, Restore regelmäßig testen und einen Wiederherstellungsablauf dokumentieren.

## Datenmodell (erste Version)

### `licenses`
- `id`
- `name`, `vendor`, `product_version`
- `license_type` (perpetual, subscription, trial, other)
- `secret_ciphertext` (verschlüsselter Lizenzschlüssel)
- `purchase_date`, `purchase_price`, `currency`
- `expires_at`, `renewal_date`, `device_limit`
- `category_id`, `notes`
- `created_at`, `updated_at`

### `homekit_devices`
- `id`
- `name`, `manufacturer`, `model`
- `room`, `status`
- `setup_code_ciphertext` (verschlüsselter Kopplungs-/Einrichtungscode)
- `qr_payload_ciphertext` (falls der QR-Payload separat aufbewahrt wird)
- `notes`, timestamps

### Begleitende Tabellen
- `categories`
- `tags` und Pivot-Tabelle für Lizenzen/Geräte
- `attachments` mit internem Speicherpfad, MIME-Typ, Dateigröße und Besitzerzuordnung
- `reminders` für Ablauf-/Verlängerungshinweise
- optional `activity_logs` ohne Klartext-Geheimnisse

HomeKit-QR-Codes können aus dem gespeicherten Payload bei Bedarf erzeugt werden, sofern das Format unterstützt wird. Keine realen HomeKit-Codes in Seedern, Screenshots, Tests oder Demo-Daten verwenden.

## Funktionsumfang nach Priorität

### Phase 0 – Repository und technische Basis
- GitHub-Repository und README
- Dockerfile, `compose.yaml`, `compose.local-db.yaml`, `.dockerignore`
- Laravel-Grundgerüst, Healthcheck und Konfigurationsvalidierung
- `.env.example` ohne echte Geheimnisse
- CI: Format-/Lint-Prüfungen und automatisierte Tests

**Abnahmekriterien:** App startet im lokalen Compose-Setup; DB-Modus ist dokumentiert; Secrets sind nicht im Repository; Healthcheck ist erfolgreich.

### Phase 1 – Authentifizierung und Designsystem
- Registrierung deaktivieren; erstes Admin-Konto über dokumentierten sicheren Setup-Prozess erzeugen
- Login/Logout, Passwortänderung und Rate-Limits
- Sidebar, Topbar, responsive Layout, Light-/Dark-Mode
- UI-Komponenten für Tabellen, Karten, Formulare, Filter und Bestätigungsdialoge

**Abnahmekriterien:** Ohne Anmeldung keine privaten Seiten oder Dateien; Darstellung auf Desktop und Mobilgerät benutzbar.

### Phase 2 – Lizenzverwaltung
- CRUD für Lizenzen
- Kategorien, Tags, Suche und Filter
- Lizenzschlüssel verschlüsselt speichern und kontrolliert ein-/ausblenden
- Ablauf- und Verlängerungsdaten, Kaufpreis und Gerätelimit

**Abnahmekriterien:** Validierung, Autorisierung und Feature-Tests für Erstellen, Bearbeiten, Löschen und Geheimnisanzeige.

### Phase 3 – HomeKit-Galerie
- CRUD für Geräte, Räume und Hersteller
- QR-Code-Generierung bzw. Upload eines QR-Bildes
- Einrichtungscode und Payload geschützt speichern
- QR-Detailansicht, Druckansicht und Suche

**Abnahmekriterien:** Codes sind nur nach Login sichtbar; QR-Bild und hinterlegte Daten lassen sich nach Backup wiederherstellen.

### Phase 4 – Dokumente, Dashboard und Erinnerungen
- Geschützte Rechnungen und Anhänge
- Kennzahlen und zuletzt geänderte Einträge
- Hinweise auf bald ablaufende Lizenzen
- Laravel Scheduler; Benachrichtigungskanal zunächst in-app, E-Mail optional später

**Abnahmekriterien:** Dokumente werden nicht öffentlich ausgeliefert; Fälligkeitslogik ist durch Tests abgedeckt.

### Phase 5 – Backup, Restore und Release
- Datenbank-Dump und Dateiarchiv
- Backup-Export mit Versions-/Zeitstempel
- Restore mit Validierung, Vorschau/Bestätigung und Rollback-Strategie
- GitHub Actions und versioniertes Release-Image, optional GHCR
- Unraid-Installations- und Updateanleitung

**Abnahmekriterien:** Restore-Test in einer getrennten Umgebung erfolgreich; App- und DB-Updates sind dokumentiert; Wiederherstellungsschlüssel sind verfügbar.

## Tests und Arbeitsweise

- Jede Phase separat implementieren und abschließen.
- Nach jeder Phase Tests ausführen, Änderungen prüfen und einen Conventional Commit erstellen.
- Backend-Validierung und Berechtigungen sind maßgeblich; UI-Validierung ersetzt keine Serverprüfung.
- Feature-Tests für Login, Datenzugriff, Verschlüsselung, Uploads, QR-Code-Zugriff, Ablaufdaten und Backup/Restore.
- Tests nutzen ausschließlich synthetische Daten.
- Keine echten Schlüssel oder QR-Payloads in Screenshots, Logs oder Test-Fixtures.
- Vor jedem Datenbank- oder Schemawechsel ein Backup erstellen.

## Repository-Vorschlag

```text
licensevault/
├── app/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
├── routes/
├── tests/
├── docker/
│   └── nginx/
├── compose.yaml
├── compose.local-db.yaml
├── Dockerfile
├── .dockerignore
├── .env.example
├── .gitignore
└── README.md
```

## Erste Umgebungsvariablen

```dotenv
APP_NAME=LicenseVault
APP_ENV=production
APP_DEBUG=false
APP_URL=https://licensevault.example.local
APP_KEY=
APP_TIMEZONE=Europe/Berlin

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=licensevault
DB_USERNAME=licensevault
DB_PASSWORD=
DB_SSLMODE=prefer

FILESYSTEM_DISK=local
SESSION_SECURE_COOKIE=true
```

`APP_KEY` wird mit dem vorgesehenen Laravel-Setup sicher erzeugt. `APP_URL` ist auf die tatsächliche interne URL bzw. Reverse-Proxy-Adresse anzupassen. `SESSION_SECURE_COOKIE=true` setzt HTTPS voraus; bei rein lokalem HTTP-Entwicklungsbetrieb kann die Einstellung für die lokale Umgebung überschrieben werden. Die Beispielvariablen sind noch keine vollständige produktive Compose-Konfiguration.

## Nicht-Ziele für Version 1

- Mehrbenutzerbetrieb oder Mandantenfähigkeit
- Öffentliche Registrierung
- Native iOS-App
- Synchronisierung mit Apple Home oder iCloud
- Automatischer Import von Lizenzdaten aus Händlerkonten
- Komplexe Rollen-/Rechteverwaltung

## Definition of Done

Ein Feature gilt erst als abgeschlossen, wenn es validiert, autorisiert, getestet, in der UI erreichbar und dokumentiert ist. Daten mit Geheimnissen dürfen weder versehentlich öffentlich werden noch in Logs oder Backups ungeschützt abgelegt sein. Ein Release ist erst bereit, wenn der dokumentierte Backup-/Restore-Test erfolgreich war.

## Schnellstart (Phase 0)

```bash
cp .env.example .env
# APP_KEY erzeugen und DB_PASSWORD setzen (kein Platzhalter!)
docker compose -f compose.yaml -f compose.local-db.yaml build
docker compose run --rm --no-deps -e APP_KEY=x -e DB_PASSWORD=x app php artisan key:generate --show
docker compose -f compose.yaml -f compose.local-db.yaml up -d
curl http://localhost:8080/up   # Healthcheck
```

`RUN_MIGRATIONS=true` führt beim Start Schema-Migrationen aus (nur nach Backup aktivieren; Standard ist `false`). Die Konfigurationsvalidierung im Entrypoint bricht ab, wenn `APP_KEY`/`DB_PASSWORD` fehlen oder noch Platzhalter sind.

## Design-Entscheidung

UI-Mockup siehe `docs/mockup/LicenseVault-Mockup.png`. Der Hero-Bereich (Login und Dashboard-Kopf) erhält einen **Glas-Look** (Glassmorphism: Backdrop-Blur, transluzente Flächen, feine Lichtkante, weicher Farbverlauf-Hintergrund) – in Light und Dark. Datenlastige Bereiche (Tabellen, Formulare) bleiben flach und kontrastreich für Lesbarkeit.

## Status

- Phase 0 (Docker/Compose, Healthcheck, Konfigurationsvalidierung): fertig.
- Phase 1 (Login/Logout mit Rate-Limit, Passwortänderung, Admin-Setup-Befehl, Sidebar/Topbar, Light/Dark, Glas-Hero): fertig, 8 Feature-Tests grün.
- Phase 2 (Lizenzverwaltung: CRUD, Kategorien, Tags, Suche/Filter, verschlüsselter Schlüssel mit Einblenden/Kopieren, Dashboard-Kennzahlen): fertig, 18 Feature-Tests grün.
- Phase 3 (HomeKit-Galerie: Geräte-CRUD, Räume, QR aus Payload erzeugt oder als Bild hochgeladen, Einrichtungscode/Payload verschlüsselt, QR nur auf Klick geladen, `no-store`-Header, Druckansicht, Suche/Filter): fertig, 27 Feature-Tests grün.
- Phase 4 (Dokumente mit geschütztem Upload/Download, Anhänge an Lizenzen/Geräte, Erinnerungen mit täglicher Prüfung, Glocke/Zähler, Dashboard „Zuletzt geändert“): fertig, 42 Feature-Tests grün. Vorlaufzeiten über `REMINDER_DAYS` (Standard `30,7,1`).
- Phase 5 (verschlüsselte Backups mit Manifest/Prüfsummen, `licensevault:backup`/`restore` mit Dry-Run, Sicherheitskopie und Rollback, UI „Backup & Export“, CSV-Export ohne Geheimnisse, GitHub Actions CI + Release nach GHCR): fertig, 54 Feature-Tests grün, Restore auf echtem PostgreSQL getestet. Details: `docs/BACKUP.md`.
- Lizenz-Erweiterungen: Konto-Zugang (Login, verschlüsseltes Passwort, URL), 2FA-/Wiederherstellungscodes (verschlüsselt), Kundennummer, Support-Kontakt, Plattformen, Installiert-auf-Geräte, Icon-Upload, Listen-/Kachelansicht, Filter nach Plattform/Gerät: 64 Feature-Tests grün.
- PWA/iPhone: installierbar (Manifest, Icons, mobile Navigation), HomeKit-QR per Kamera scannen (jsQR im Browser, Einrichtungscode und Kategorie werden aus dem Payload abgeleitet, Foto-Fallback ohne HTTPS): 68 Feature-Tests grün; Live-Kamera nur mit HTTPS, siehe `docs/PWA.md`.
- HomeKit & Matter: Geräte mit Standard-Auswahl (HomeKit/Matter), Matter-QR (`MT:`) und manueller Kopplungscode mit Prüfziffer, Scanner erkennt beide Formate, Filter nach Standard: 76 Feature-Tests grün.
- Geräte: Art wählbar (HomeKit / Matter / HomeKit + Matter, mit Filter-Einschluss), Gerätefoto mit Verkleinerung im Browser, Anzeige in Galerie und Detail: 82 Feature-Tests grün.
- Bild-Uploads (Lizenz-Icon, Gerätefoto, QR-Bild, Dokumente) erlauben jetzt auch HEIC/HEIF (Safari wandelt im Browser nach JPEG, sonst wird das Original gespeichert; Inhalt wird geprüft): 84 Feature-Tests grün.
- Lizenzen: Kachelansicht ist Standard (Wahl wird per Cookie gemerkt). Benachrichtigungen bei fälligen Lizenzen per Web-Push (VAPID, iPhone-PWA ab iOS 16.4) und Gotify, Einstellungsseite mit Testnachricht, Details in `docs/NOTIFICATIONS.md`: 98 Feature-Tests grün; Web-Push (VAPID + aes128gcm) und Gotify gegen lokale Gegenstellen geprüft, Apples Push-Dienst und echtes iPhone nicht.
- PDF-Export der HomeKit-/Matter-QR-Codes (einzeln und Liste, dompdf) für Teilen/Drucken in der iPhone-App; `lvPrint` öffnet dort statt `window.print()` das PDF: 102 Feature-Tests grün, PDF-Ausgabe im Container geprüft.
- Unraid: All-in-One-Image (`--target aio`) = EIN Container mit nginx, PHP-FPM, Scheduler und eingebettetem PostgreSQL 16, ein Volume `/data`, automatische Schlüssel/DB-Einrichtung, Admin beim ersten Start aus Variablen; Templates in `unraid/`, Anleitung `docs/UNRAID-TEMPLATE.md`. Im Container getestet: Erststart, Login, Backup/Restore, Neustart mit Persistenz.
- Update auf Phase 2–5 (Unraid): Backup, Dateien aktualisieren, `RUN_MIGRATIONS=true` setzen, `docker compose ... up -d --build`, danach wieder `false`.
- Unraid-Anleitung: `docs/UNRAID.md`.
