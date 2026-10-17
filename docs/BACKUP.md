# Backup, Restore und Updates

## Was gesichert wird

Eine Sicherung (`*.lvbak`) ist **verschlüsselt** (XChaCha20-Poly1305, Schlüssel per Argon2id aus `BACKUP_PASSPHRASE`) und enthält:

- `db.dump` – PostgreSQL-Dump (intern oder extern, über `DB_*`)
- `uploads/` – Dokumente und QR-Bilder
- `manifest.json` – Version, Zeitstempel, Datensatzzahlen, SHA-256-Prüfsummen, Fingerabdruck des `APP_KEY`

**Nicht enthalten:** `APP_KEY` und `BACKUP_PASSPHRASE`. Bewahre beide **getrennt** von den Sicherungen auf (z. B. im Passwort-Manager). Ohne `APP_KEY` sind verschlüsselte Lizenzschlüssel und Einrichtungscodes auch nach einem Restore nicht lesbar; ohne Passphrase lässt sich die Sicherung nicht öffnen.

## Einrichten

In `.env`:

```dotenv
BACKUP_PASSPHRASE=<mind. 12 Zeichen, zufällig>   # Pflicht, sonst keine Sicherungen
BACKUP_KEEP=14                                   # wie viele Sicherungen behalten werden
BACKUP_TIME=03:00                                # tägliche Uhrzeit (APP_TIMEZONE)
```

Container neu erzeugen (`docker compose … up -d --force-recreate`). Der `scheduler`-Container sichert dann täglich nach `DATA_DIR/backups/`. Zusätzlich: in der Oberfläche unter **Backup & Export** („Jetzt sichern“, Prüfen, Download, CSV-Export) oder per Befehl:

```bash
docker compose -f compose.yaml exec -u app app php artisan licensevault:backup
```

**Wichtig:** `DATA_DIR/backups` liegt auf demselben Medium wie die App. Kopiere die Dateien zusätzlich auf ein anderes Medium oder System (z. B. Unraid-Share auf anderer Platte, Cloud-Sync, Rsync).

## Wiederherstellen (nur per Kommandozeile)

1. Sicherung prüfen – verändert nichts:

   ```bash
   docker compose -f compose.yaml exec -u app app php artisan licensevault:restore <datei>.lvbak --dry-run
   ```

   Zeigt Datum, Version, Datensatzzahlen und prüft Entschlüsselung, Prüfsummen und Kompatibilität (unbekannte Migrationen, abweichender `APP_KEY`).

2. Einspielen (mit Rückfrage; `--force` überspringt sie):

   ```bash
   docker compose -f compose.yaml exec -u app app php artisan licensevault:restore <datei>.lvbak
   ```

   - Vorher entsteht automatisch eine Sicherheitskopie `…-pre-restore.lvbak` des aktuellen Stands.
   - Die Datenbank wird in **einer Transaktion** eingespielt: schlägt etwas fehl, bleibt sie unverändert.
   - Schlägt der Restore fehl, wird automatisch auf die Sicherheitskopie zurückgerollt.
   - Ältere Sicherungen werden anschließend per Migration auf das aktuelle Schema gebracht.
   - Weicht der `APP_KEY` ab, bricht der Befehl ab (außer mit `--ignore-key-mismatch`).

3. Rollback von Hand: dieselbe Prozedur mit der `…-pre-restore.lvbak`-Datei.

### Restore in einer getrennten Umgebung testen

Starte einen zweiten Stack (eigener `DATA_DIR`, eigene Datenbank, anderer `WEB_PORT`) mit demselben `APP_KEY` und `BACKUP_PASSPHRASE`, kopiere die `.lvbak` nach `DATA_DIR/backups/` und führe `licensevault:restore` aus. Melde dich an und prüfe Lizenzen, Schlüssel-Anzeige, Dokumente und QR-Codes. Wiederhole den Test regelmäßig.

## Update der App

1. Sicherung erstellen und sichern (siehe oben).
2. Dateien aktualisieren (oder neues Image: `LV_VERSION` erhöhen).
3. `RUN_MIGRATIONS=true` setzen, `docker compose … up -d --build` (bzw. mit `-f compose.release.yaml`), danach `RUN_MIGRATIONS=false`.

## Release-Images (GHCR)

Ein Git-Tag `vX.Y.Z` löst `.github/workflows/release.yml` aus und veröffentlicht `ghcr.io/<owner>/licensevault-app` und `…-web` (UID 99 / GID 100 für Unraid). Nutzung:

```bash
LV_VERSION=0.6.1 docker compose -f compose.yaml -f compose.release.yaml up -d
```

## Hinweis zu PostgreSQL-Versionen

Der Container bringt den aktuellen PostgreSQL-Client mit. Dumps funktionieren gegen gleich alte oder ältere Server; der Restore filtert versionsspezifische Einstellungen heraus, damit Dumps des neuen Clients auch in PostgreSQL 16 laufen. Läuft dein Server **neuer** als der Client im Image, aktualisiere das Image.
