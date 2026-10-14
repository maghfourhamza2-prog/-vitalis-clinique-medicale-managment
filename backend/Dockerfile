# syntax=docker/dockerfile:1

# Laravel 13 requires PHP >= 8.3; 8.4 is the current stable branch.
FROM php:8.4-fpm-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_NO_INTERACTION=1

# System packages needed to build the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# PostgreSQL (pdo_pgsql/pgsql) + the usual Laravel extension set.
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        mbstring \
        exif \
        pcntl \
        sockets \
        opcache

# Redis extension (phpredis) - Laravel's REDIS_CLIENT=phpredis.
RUN pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first so the layer is cached across source changes.
COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --no-autoloader

# Placed after `composer install` so editing php.ini/www.conf or rebuilding
# with different UID/GID no longer invalidates the dependency layer.
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

ARG UID=1000
ARG GID=1000

# Align the container user with the host user so bind-mounted files stay writable.
RUN if [ "$GID" != "82" ] && [ "$GID" != "0" ]; then groupmod -o -g "$GID" www-data; fi \
    && if [ "$UID" != "82" ] && [ "$UID" != "0" ]; then usermod -o -u "$UID" -g "$GID" www-data; fi

COPY . .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache/data \
                storage/framework/sessions \
                storage/framework/views \
                storage/logs \
                bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/entrypoint

USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "--nodaemonize"]
