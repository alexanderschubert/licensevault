#!/bin/sh
# LicenseVault All-in-One: bereitet /data vor, startet bei Bedarf ein eingebettetes PostgreSQL,
# führt Migrationen aus und übergibt an supervisord (nginx + PHP-FPM + Scheduler [+ PostgreSQL]).
set -eu

DATA=/data
APP=/var/www/html
log() { echo "[licensevault] $*"; }
die() { echo "[licensevault] FEHLER: $*" >&2; exit 1; }

[ -d "$DATA" ] || die "Das Volume /data fehlt (Pfad im Template/Container zuordnen)."

# ---- Verzeichnisse: alles Persistente liegt unter /data ------------------------------------
mkdir -p "$DATA/config" "$DATA/storage" "$DATA/uploads" "$DATA/exports" "$DATA/backups"
for d in app/private/tmp framework/cache framework/sessions framework/views logs; do mkdir -p "$DATA/storage/$d"; done
rm -rf "$APP/storage" && ln -s "$DATA/storage" "$APP/storage"
for d in uploads exports backups; do
  t="$DATA/storage/app/private/$d"
  if [ -d "$t" ] && [ ! -L "$t" ]; then rmdir "$t" 2>/dev/null || true; fi
  ln -sfn "$DATA/$d" "$t"
done
for d in storage uploads exports backups; do
  [ "$(stat -c %u "$DATA/$d")" = "$(id -u app)" ] || chown -R app:"$(id -gn app)" "$DATA/$d"
done
chmod 700 "$DATA/config"

# ---- APP_KEY: übernehmen oder einmalig erzeugen --------------------------------------------
if [ -z "${APP_KEY:-}" ]; then
  if [ ! -s "$DATA/config/app.key" ]; then
    (umask 077; php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$DATA/config/app.key")
    log "APP_KEY wurde erzeugt und in $DATA/config/app.key gespeichert."
    log ">>> SICHERE DIESEN SCHLÜSSEL ZUSÄTZLICH getrennt (Passwort-Manager): $(cat "$DATA/config/app.key")"
    log ">>> Ohne ihn sind verschlüsselte Daten nach einem Verlust von /data nicht wiederherstellbar."
  fi
  APP_KEY="$(cat "$DATA/config/app.key")"
fi
export APP_KEY

# ---- Datenbank: eingebettet (Standard) oder extern -----------------------------------------
if [ "${EMBEDDED_DB:-true}" = "true" ]; then
  EMBEDDED_DB=true
else
  EMBEDDED_DB=false
fi
export EMBEDDED_DB
FRESH=false

PGBIN="$(dirname "$(ls /usr/libexec/postgresql*/initdb 2>/dev/null | head -1)" 2>/dev/null || true)"
{ [ -n "$PGBIN" ] && [ -x "$PGBIN/initdb" ]; } || PGBIN=/usr/bin
export PGBIN

if [ "$EMBEDDED_DB" = "true" ]; then
  export DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=licensevault DB_USERNAME=licensevault DB_SSLMODE=disable
  if [ ! -s "$DATA/config/db.pass" ]; then
    (umask 077; head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$DATA/config/db.pass")
  fi
  DB_PASSWORD="$(cat "$DATA/config/db.pass")"; export DB_PASSWORD

  PGDATA="$DATA/postgres"
  mkdir -p "$PGDATA" /run/postgresql
  chown postgres:postgres "$PGDATA" /run/postgresql; chmod 700 "$PGDATA"
  if [ ! -s "$PGDATA/PG_VERSION" ]; then
    log "Initialisiere eingebettetes PostgreSQL …"
    su-exec postgres "$PGBIN/initdb" -D "$PGDATA" -E UTF8 --locale=C.UTF-8 --auth-local=trust --auth-host=scram-sha-256 >/dev/null
    FRESH=true
  fi
  PGOPTS="-c listen_addresses=127.0.0.1 -c unix_socket_directories=/run/postgresql"
  su-exec postgres "$PGBIN/pg_ctl" -D "$PGDATA" -o "$PGOPTS" -w -t 120 start >/dev/null
  if [ "$FRESH" = "true" ]; then
    su-exec postgres "$PGBIN/psql" -h /run/postgresql -d postgres -v ON_ERROR_STOP=1 -q \
      -c "CREATE ROLE licensevault LOGIN PASSWORD '$DB_PASSWORD'" \
      -c "CREATE DATABASE licensevault OWNER licensevault ENCODING 'UTF8' TEMPLATE template0"
  fi
else
  [ -n "${DB_HOST:-}" ] && [ -n "${DB_PASSWORD:-}" ] || die "EMBEDDED_DB=false braucht DB_HOST, DB_DATABASE, DB_USERNAME und DB_PASSWORD."
  export DB_CONNECTION=pgsql
  case "$DB_PASSWORD" in CHANGE_ME*) die "DB_PASSWORD ist noch der Platzhalter";; esac
  i=0
  until pg_isready -h "$DB_HOST" -p "${DB_PORT:-5432}" -t 3 >/dev/null 2>&1; do
    i=$((i + 1)); [ "$i" -le 40 ] || die "Datenbank $DB_HOST:${DB_PORT:-5432} nicht erreichbar."
    sleep 3
  done
fi

# ---- Migrationen (bei frischer DB immer, sonst nach RUN_MIGRATIONS) ------------------------
cd "$APP"
if [ "$FRESH" = "true" ] || [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  if [ "$FRESH" != "true" ] && [ "${#BACKUP_PASSPHRASE}" -ge 12 ] 2>/dev/null \
     && su-exec app php artisan migrate:status --pending --no-ansi 2>/dev/null | grep -q "Pending"; then
    log "Ausstehende Migrationen gefunden – erstelle vorher eine Sicherung …"
    su-exec app php artisan licensevault:backup || log "WARNUNG: Sicherung vor der Migration fehlgeschlagen."
  fi
  su-exec app php artisan migrate --force --no-interaction
fi

# ---- Administrator beim ersten Start aus Variablen anlegen ---------------------------------
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
  if su-exec app php artisan licensevault:create-admin --name="${ADMIN_NAME:-Admin}" --email="$ADMIN_EMAIL" --password-env=ADMIN_PASSWORD; then
    log "Administrator angelegt. Entferne jetzt ADMIN_PASSWORD aus der Container-Konfiguration."
  fi
fi

[ "${#BACKUP_PASSPHRASE}" -ge 12 ] 2>/dev/null || log "Hinweis: BACKUP_PASSPHRASE fehlt/zu kurz – automatische Sicherungen sind deaktiviert."

su-exec app php artisan config:cache >/dev/null
su-exec app php artisan route:cache >/dev/null
su-exec app php artisan view:cache >/dev/null

# Eingebettetes PostgreSQL: temporäre Instanz beenden, supervisord startet die dauerhafte
if [ "$EMBEDDED_DB" = "true" ]; then
  su-exec postgres "$PGBIN/pg_ctl" -D "$DATA/postgres" -m fast -w -t 120 stop >/dev/null
fi

log "Starte LicenseVault (Port 8080) …"
exec supervisord -c /etc/supervisord.conf
