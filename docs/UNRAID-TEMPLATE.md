# LicenseVault als EIN Container auf Unraid (Template)

Das **All-in-One-Image** (`--target aio`) bringt alles in einem Container mit: Web-Oberfläche (nginx + PHP), Scheduler (Erinnerungen, nächtliche Backups) und ein **eingebettetes PostgreSQL 16**. In Unraid erscheint nur **ein** Container; alles Persistente liegt in **einem** Verzeichnis (`/data`). Den Mehr-Container-Stack (`compose.yaml`) gibt es weiterhin, falls du eine vorhandene Datenbank oder PostgreSQL ab Version 17 nutzen willst.

## 1. Image bereitstellen

**Variante A – lokal auf dem Unraid bauen** (ohne Registry):

```bash
cd /mnt/user/appdata/licensevault
docker build --target aio -t licensevault:0.6.0 .
```

(Das Image läuft als `nobody:users` = UID 99 / GID 100, passend zu Unraid.)

**Variante B – fertiges Image aus GHCR** (nach einem Release-Tag `vX.Y.Z`, siehe `.github/workflows/release.yml`): `ghcr.io/<github-benutzer>/licensevault:<version>`.

## 2. Template installieren

1. Die passende Datei nach `/boot/config/plugins/dockerMan/templates-user/` kopieren:
   - `unraid/licensevault-lokal.xml` (Variante A) oder `unraid/licensevault.xml` (Variante B, Image `ghcr.io/alexanderschubert/licensevault`; bei einem **privaten** Repo vorher auf dem Unraid `docker login ghcr.io` mit einem Token mit `read:packages` ausführen).

   ```bash
   cp /mnt/user/appdata/licensevault/unraid/licensevault-lokal.xml /boot/config/plugins/dockerMan/templates-user/my-licensevault.xml
   ```

2. Unraid → **Docker** → **Add Container** → oben bei **Template** „LicenseVault“ wählen.
3. Ausfüllen:
   - **Daten:** z. B. `/mnt/user/appdata/licensevault` (dort liegen `postgres/`, `storage/`, `uploads/`, `backups/`, `exports/`, `config/`).
   - **APP_URL:** die Adresse, unter der du LicenseVault öffnest, z. B. `http://192.168.1.20:8080`.
   - **Admin: E-Mail / Passwort / Name:** legt **beim ersten Start** das Konto an (Passwort mind. 12 Zeichen, Groß-/Kleinbuchstaben, Zahl).
   - **Backup-Passphrase:** mind. 12 Zeichen; aktiviert die täglichen verschlüsselten Sicherungen.
4. **Apply**. Nach dem Start öffnet der Knopf **WebUI** die Oberfläche; anmelden mit der gewählten E-Mail.
5. **Danach `ADMIN_PASSWORD` aus dem Container entfernen** (Container bearbeiten → Variable löschen → Apply). Es wird nur beim allerersten Start gebraucht.

## 3. Wichtig: Schlüssel sichern

Beim ersten Start erzeugt der Container einen **APP_KEY** (steht im Container-Log und in `/data/config/app.key`). Damit sind Lizenzschlüssel und Codes verschlüsselt. **Kopiere ihn zusätzlich in deinen Passwort-Manager** und sichere auch die **Backup-Passphrase** getrennt: Ohne den Schlüssel sind die Daten nach einem Verlust von `/data` nicht lesbar. Du kannst den Schlüssel auch im Template unter *APP_KEY* fest eintragen.

## 4. HTTPS, iPhone, Benachrichtigungen

- Reverse Proxy (Nginx Proxy Manager) → `http://<unraid-ip>:8080`; dann im Template `APP_URL=https://…` und `SESSION_SECURE_COOKIE=true` setzen. Details: `PWA.md`.
- Web-Push/Gotify: Variablen im Template (Bereich „Erweitert“); Einrichtung siehe `NOTIFICATIONS.md`. VAPID-Schlüssel erzeugen:

  ```bash
  docker exec -u app LicenseVault php artisan licensevault:vapid-keys
  ```

## 5. Updates, Backups, Restore

- **Update:** neues Image bauen/ziehen (Version erhöhen), Container neu erzeugen bzw. in Unraid „Update“. Ausstehende Datenbank-Updates laufen beim Start automatisch; ist eine **Backup-Passphrase** gesetzt, wird **vorher eine Sicherung** erstellt (`RUN_MIGRATIONS=false` schaltet das ab).
- **Backups:** täglich 03:00 Uhr nach `/data/backups` (verschlüsselt, `BACKUP_KEEP` Stück). Kopiere sie zusätzlich auf ein anderes Medium. Manuell und Restore:

  ```bash
  docker exec -u app LicenseVault php artisan licensevault:backup
  docker exec -u app LicenseVault php artisan licensevault:restore <datei>.lvbak --dry-run
  ```

  Mehr dazu in `BACKUP.md`.
- **Externe Datenbank statt eingebetteter:** `EMBEDDED_DB=false` und `DB_*` setzen. Der Container enthält den PostgreSQL-16-Client – für Datenbankserver ab Version 17 funktionieren Backups damit nicht; nutze dann den Mehr-Container-Stack.
- **Anderer Container-Name:** In den Befehlen oben `LicenseVault` durch den Namen deines Containers ersetzen.

## Was läuft im Container?

`supervisord` startet PostgreSQL (nur bei eingebetteter DB), PHP-FPM, nginx (Port 8080) und eine Scheduler-Schleife. Logs erscheinen gesammelt im Container-Log (Unraid: Docker → Log-Symbol). Der Healthcheck prüft `/up`.
