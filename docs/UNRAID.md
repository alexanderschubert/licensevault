# LicenseVault auf Unraid starten

Voraussetzung: Docker aktiv, optional das Plugin **Docker Compose Manager** (oder per SSH/Terminal).
Dieses Projekt liegt unter `/mnt/user/appdata/licensevault` (Share `appdata`).

## 1. Konfiguration

```bash
cd /mnt/user/appdata/licensevault
cp .env.example .env
```

In `.env` anpassen:

| Variable | Wert |
|---|---|
| `APP_KEY` | siehe Schritt 2 |
| `DB_PASSWORD` | langes Zufallspasswort (`openssl rand -hex 24`) |
| `APP_URL` | z. B. `http://192.168.1.20:8080` |
| `SESSION_SECURE_COOKIE` | `false` solange du **ohne HTTPS** (nur per IP) zugreifst, sonst `true` |
| `DATA_DIR` | `/mnt/user/appdata/licensevault/data` |
| `WEB_PORT` | freier Host-Port, falls 8080 belegt ist |
| `PUID` / `PGID` | `99` / `100` (Unraid `nobody:users`) |
| `BACKUP_PASSPHRASE` | mind. 12 Zeichen, getrennt sichern (siehe `docs/BACKUP.md`) |
| `RUN_MIGRATIONS` | `true` für den ersten Start (danach `false`) |

## 2. Bauen, Schlüssel erzeugen, starten (Modus A: eigene Datenbank)

```bash
docker compose -f compose.yaml -f compose.local-db.yaml build
docker compose -f compose.yaml run --rm --no-deps -e APP_KEY=x -e DB_PASSWORD=x app php artisan key:generate --show
```

Den ausgegebenen Wert (`base64:...`) als `APP_KEY` in `.env` eintragen. **Sichere ihn separat** – ohne ihn sind spätere verschlüsselte Daten nicht wiederherstellbar.

```bash
docker compose -f compose.yaml -f compose.local-db.yaml up -d
docker compose -f compose.yaml -f compose.local-db.yaml exec -u app app php artisan licensevault:create-admin --name="Dein Name"
```

Danach: `http://<unraid-ip>:8080` öffnen und anmelden.

Modus B (vorhandenes PostgreSQL): `DB_HOST` auf die Instanz setzen, eigene DB + eigenen Benutzer anlegen und nur `compose.yaml` verwenden.

## Hinweise

- Beim Bauen auf Unraid wird Internetzugang für Docker Hub/Packagist/npm benötigt (erster Build einige Minuten).
- Zugriff von außen nur über einen Reverse Proxy mit HTTPS (z. B. Nginx Proxy Manager → `http://<unraid-ip>:8080`) und dann `SESSION_SECURE_COOKIE=true`.
- Update: Backup erstellen, Dateien aktualisieren, `docker compose ... up -d --build`.

- Backups, Restore und Updates: siehe [BACKUP.md](BACKUP.md).
