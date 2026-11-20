FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json ./
RUN npm install --no-audit --no-fund

COPY . .
RUN npm run build

FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ffmpeg \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        supervisor \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && a2enmod headers rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/sites-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
    && composer dump-autoload --no-dev --no-interaction --optimize \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && printf '%s\n' \
        'upload_max_filesize=220M' \
        'post_max_size=230M' \
        'memory_limit=512M' \
        'max_execution_time=900' \
        > /usr/local/etc/php/conf.d/uploads.ini \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/supervisord.conf /etc/supervisor/conf.d/laravel.conf

RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["web"]
