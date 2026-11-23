# syntax=docker/dockerfile:1

# ==============================================================================
# Stage 1: Base PHP 8.4 + Extensiones del Sistema DAGAZ
# ==============================================================================
FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        pdo_mysql \
        exif \
        bcmath \
        pcntl \
        intl \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# ==============================================================================
# Stage 2: Dependencias PHP de Producción (Composer)
# ==============================================================================
FROM php-base AS composer-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app/backend

COPY backend/composer.json backend/composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --no-progress

# ==============================================================================
# Stage 3: Compilación de Assets Frontend (Vite 8 + Tailwind CSS v4)
# ==============================================================================
FROM node:22-bookworm-slim AS frontend-assets

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app/backend

COPY backend/package.json backend/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY backend/vite.config.js ./
COPY backend/resources ./resources
COPY frontend /app/frontend
COPY --from=composer-deps /app/backend/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views

RUN mkdir -p public && npm run build

# ==============================================================================
# Stage 4: Runtime de Producción (Nginx + PHP-FPM 8.4)
# ==============================================================================
FROM php-base AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
    && rm -rf /etc/nginx/sites-enabled/default /var/lib/apt/lists/* \
    && mkdir -p /etc/nginx/templates

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-dagaz.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-dagaz.conf
COPY docker/nginx/default.conf.template /etc/nginx/templates/dagaz.conf.template
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/dagaz-entrypoint

RUN sed -i 's/\r$//' /usr/local/bin/dagaz-entrypoint \
    && chmod +x /usr/local/bin/dagaz-entrypoint

WORKDIR /app/backend

COPY backend /app/backend
COPY frontend/views /app/frontend/views
COPY --from=composer-deps /app/backend/vendor /app/backend/vendor
COPY --from=frontend-assets /app/backend/public/build /app/backend/public/build

RUN rm -f bootstrap/cache/*.php \
    && mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data /app/backend/storage /app/backend/bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    TRUSTED_PROXIES="*" \
    OCR_SERVICE_URL=disabled \
    STT_SERVICE_URL=disabled \
    PORT=8000

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=25s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT:-8000}/api/health" >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/dagaz-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
