FROM php:8.4-fpm-alpine

RUN apk add --no-cache     git curl zip unzip bash     libpng-dev libjpeg-dev freetype-dev     oniguruma-dev libxml2-dev     nodejs npm

RUN docker-php-ext-install     pdo_mysql mbstring exif pcntl bcmath gd xml

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
