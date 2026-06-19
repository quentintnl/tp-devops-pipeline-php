# ─────────────────────────────────────────────────────────────────────────────
# Image de l'app `app` = PHP-FPM (Guestbook).
# Multi-stage : (1) composer install des deps, (2) runtime php-fpm Alpine non-root.
# ─────────────────────────────────────────────────────────────────────────────

# --- Stage 1 : dépendances Composer (vendor/) ---------------------------------
FROM composer:2 AS deps
WORKDIR /app
# On copie d'abord les manifestes pour profiter du cache de layers Docker.
COPY composer.json composer.lock ./
# Pas d'autoload des classes app ici (src/ pas encore copié) → --no-scripts.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-progress
# Puis le code, et on (re)génère l'autoload optimisé maintenant que src/ existe.
COPY src/ ./src/
COPY public/ ./public/
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# --- Stage 2 : runtime PHP-FPM ------------------------------------------------
FROM php:8.3-fpm-alpine AS runtime

# Extension DB : PDO MySQL (MariaDB parle le protocole MySQL).
# predis = pur PHP (installé via Composer) → rien à compiler ici.
RUN docker-php-ext-install pdo_mysql

# fcgi : fournit `cgi-fcgi`, utilisé par le HEALTHCHECK pour pinger le pool php-fpm.
RUN apk add --no-cache fcgi

WORKDIR /var/www/html

# Code applicatif + vendor/ (avec autoload optimisé) depuis le stage deps.
COPY --chown=www-data:www-data src/ ./src/
COPY --chown=www-data:www-data public/ ./public/
COPY --chown=www-data:www-data composer.json composer.lock ./
COPY --from=deps --chown=www-data:www-data /app/vendor/ ./vendor/

# Active un endpoint de ping sur le pool php-fpm (utilisé par le HEALTHCHECK).
COPY docker/php/zz-healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf
COPY docker/php/healthcheck.sh /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# php-fpm tourne en non-root (l'image définit l'utilisateur www-data).
USER www-data

EXPOSE 9000

# HEALTHCHECK fonctionnel : ping le pool php-fpm via FastCGI (cgi-fcgi → /ping).
# Vérifie que le process php-fpm répond réellement, pas juste qu'il est lancé.
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD ["php-fpm-healthcheck"]

CMD ["php-fpm"]
