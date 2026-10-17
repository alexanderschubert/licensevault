#!/bin/sh
set -eu

fail() { echo "Konfigurationsfehler: $1" >&2; exit 1; }

# Konfigurationsvalidierung (keine Secrets in der Ausgabe)
[ -n "${APP_KEY:-}" ]      || fail "APP_KEY fehlt (docker compose run --rm app php artisan key:generate --show)"
[ -n "${DB_PASSWORD:-}" ]  || fail "DB_PASSWORD fehlt"
case "${DB_PASSWORD}" in CHANGE_ME*) fail "DB_PASSWORD ist noch der Platzhalter";; esac
[ "${DB_CONNECTION:-}" = "pgsql" ] || fail "DB_CONNECTION muss pgsql sein"

bp="${BACKUP_PASSPHRASE:-}"
[ "${#bp}" -ge 12 ] || echo "Hinweis: BACKUP_PASSPHRASE fehlt/zu kurz – automatische Sicherungen sind deaktiviert." >&2

if [ "$(id -u)" = "0" ]; then
  mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private/uploads storage/app/private/exports
  chown -R app: /var/www/html/storage 2>/dev/null || true
fi

if [ "${1:-}" = "php-fpm" ]; then
  su-exec app php artisan storage:link >/dev/null 2>&1 || true
  su-exec app php artisan config:cache
  su-exec app php artisan route:cache
  su-exec app php artisan view:cache
  # Schema-Migrationen nur auf ausdrücklichen Wunsch (vorher Backup!)
  if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    su-exec app php artisan migrate --force
  fi
fi

# php-fpm: Master als root, Worker laufen laut Pool-Konfiguration als "app"
if [ "${1:-}" = "php-fpm" ]; then exec "$@"; fi
exec su-exec app "$@"
