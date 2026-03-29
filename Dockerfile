FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    git curl zip unzip bash \
    libpng-dev libjpeg-dev freetype-dev \
    oniguruma-dev libxml2-dev

RUN docker-php-ext-install \
    pdo_mysql mbstring exif pcntl bcmath gd xml

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─── Development stage ────────────────────────────────────────────────────────
FROM base AS development

RUN apk add --no-cache nodejs npm

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction

COPY . .
RUN composer run-script post-autoload-dump || true
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

# ─── Production stage ─────────────────────────────────────────────────────────
FROM base AS production

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
RUN composer run-script post-autoload-dump || true
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
