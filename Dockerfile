# syntax=docker/dockerfile:1.7
# Reproduzierbare, fixierte Tags – kein "latest".

# ---- Frontend-Assets (Vite/Tailwind) ----
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- PHP-Abhängigkeiten ----
FROM composer:2.10 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-req=ext-pdo_pgsql
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

# ---- App (PHP-FPM) ----
FROM php:8.5-fpm-alpine AS app
ARG UID=99
ARG GID=100
RUN apk add --no-cache libpq icu-libs libzip su-exec postgresql-client tar libpng libjpeg-turbo libwebp freetype \
 && apk add --no-cache --virtual .build libpq-dev icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev $PHPIZE_DEPS \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip pcntl bcmath gd \
 && apk del .build
# GID/UID können in Alpine schon vergeben sein (z. B. 100 = users) – dann vorhandene Gruppe/Benutzer nutzen.
RUN grep -q "^[^:]*:[^:]*:${GID}:" /etc/group || addgroup -g ${GID} -S app; \
    u=$(awk -F: -v i=${UID} '$3==i{print $1}' /etc/passwd); [ -z "$u" ] || deluser "$u"; \
    g=$(awk -F: -v i=${GID} '$3==i{print $1}' /etc/group); \
    adduser -u ${UID} -S -G "$g" app
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-licensevault.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
WORKDIR /var/www/html
COPY --from=vendor --chown=${UID}:${GID} /build ./
COPY --from=assets --chown=${UID}:${GID} /build/public/build ./public/build
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/private bootstrap/cache \
 && chown -R ${UID}:${GID} storage bootstrap/cache
# PHP-FPM-Worker laufen als Benutzer "app" (Gruppe = GID, Name kann abweichen)
RUN printf "[www]\nuser = app\ngroup = %s\nclear_env = no\n" "$(awk -F: -v i=${GID} '$3==i{print $1}' /etc/group)" > /usr/local/etc/php-fpm.d/zz-licensevault.conf
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---- Webserver (Nginx, nur statische Assets + Proxy zu PHP-FPM) ----
FROM nginx:1.28-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
# Dateirechte vom Host (z. B. SMB-Share mit 0700) normalisieren, damit nginx statische Dateien lesen darf
RUN chmod -R a+rX /var/www/html/public

# ---- All-in-One: EIN Container (nginx + PHP-FPM + Scheduler + optional eingebettetes PostgreSQL) ----
# Für Unraid & Co.: ein Volume (/data), ein Port (8080). Persistent unter /data: storage, uploads, exports,
# backups, postgres und config (automatisch erzeugte Schlüssel).
FROM app AS aio
RUN apk add --no-cache nginx supervisor postgresql16 \
 && mkdir -p /run/postgresql /run/nginx /tmp/nginx && chown postgres:postgres /run/postgresql
COPY docker/aio/nginx.conf /etc/nginx/nginx.conf
COPY docker/aio/supervisord.conf /etc/supervisord.conf
COPY docker/aio/pool.conf /usr/local/etc/php-fpm.d/zzz-aio.conf
COPY docker/aio/entrypoint.sh /usr/local/bin/aio-entrypoint.sh
RUN chmod +x /usr/local/bin/aio-entrypoint.sh && chmod -R a+rX /var/www/html/public
ENV APP_ENV=production APP_DEBUG=false APP_LOCALE=de APP_FALLBACK_LOCALE=en APP_TIMEZONE=Europe/Berlin \
    LOG_CHANNEL=stderr LOG_LEVEL=warning SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=sync \
    FILESYSTEM_DISK=local SESSION_SECURE_COOKIE=false EMBEDDED_DB=true
VOLUME ["/data"]
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1
ENTRYPOINT ["aio-entrypoint.sh"]
CMD []
