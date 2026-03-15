# ── Stage 1: Composer-Dependencies ──────────────────────────────────────────
FROM composer:2 AS composer
ARG HTTP_PROXY
ARG HTTPS_PROXY
ARG NO_PROXY
ENV HTTP_PROXY=$HTTP_PROXY HTTPS_PROXY=$HTTPS_PROXY NO_PROXY=$NO_PROXY
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ── Stage 2: Produktions-PHP-FPM ─────────────────────────────────────────────
FROM php:8.4-fpm AS production
ARG HTTP_PROXY
ARG HTTPS_PROXY
ARG NO_PROXY
ENV HTTP_PROXY=$HTTP_PROXY HTTPS_PROXY=$HTTPS_PROXY NO_PROXY=$NO_PROXY \
    http_proxy=$HTTP_PROXY https_proxy=$HTTPS_PROXY no_proxy=$NO_PROXY

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl libicu-dev libzip-dev libpq-dev linux-headers-amd64 \
  && docker-php-ext-install -j$(nproc) \
    intl zip pdo pdo_pgsql opcache pcntl bcmath \
  && rm -rf /var/lib/apt/lists/*

COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini

WORKDIR /var/www/html
COPY --from=composer /app /var/www/html

RUN mkdir -p var/cache var/log var/storage/inbox var/storage/outbox \
  && chown -R www-data:www-data var/

ENV APP_ENV=prod APP_DEBUG=0

EXPOSE 9000
# Cache-Warmup beim Start, nicht beim Build – vermeidet Probleme mit dev-only Bundles
CMD ["sh", "-c", "php bin/console cache:warmup --env=prod 2>/dev/null || true && php-fpm"]
