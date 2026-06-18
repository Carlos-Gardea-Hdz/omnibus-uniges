# syntax=docker/dockerfile:1
# ==============================================================================
# UNIGES — Production image (PHP 8.5 + Laravel 12 + precompiled Inertia/React)
# Multi-stage: frontend assets → composer vendor → runtime.
# ==============================================================================

# ── Stage 1: build frontend assets (Vite + React + Tailwind v4) ───────────────
FROM node:24-alpine AS assets
WORKDIR /app
RUN corepack enable
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN pnpm run build && pnpm run build:ssr

# ── Stage 2: composer dependencies (no dev) ───────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --ignore-platform-req=ext-pcntl

# ── Stage 3: runtime ──────────────────────────────────────────────────────────
FROM php:8.5-fpm-alpine AS production
WORKDIR /var/www/html

# System deps + PHP extensions required by the stack.
RUN apk add --no-cache curl postgresql-dev libzip-dev icu-dev linux-headers \
    && docker-php-ext-install pdo_pgsql pcntl bcmath zip intl opcache \
    && rm -rf /var/cache/apk/*

# Production OPcache (security-2026 / laravel-advanced §6).
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/bootstrap/ssr ./bootstrap/ssr

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000
HEALTHCHECK --interval=10s --timeout=5s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

# Octane/FrankenPHP can replace this; php artisan serve is the simple default.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
