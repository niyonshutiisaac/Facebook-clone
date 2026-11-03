# =============================================================================
# Laravel 11 application image (php-fpm 8.3)
# =============================================================================
FROM php:8.3-fpm-alpine AS base

# System packages: build deps + the extensions the application relies on.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk add --no-cache \
        bash \
        curl \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libwebp-dev \
        icu-dev \
        oniguruma-dev \
        ffmpeg \
        imagemagick \
        imagemagick-dev \
        mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        opcache \
        zip \
        intl \
        pcntl \
        exif \
        bcmath \
        gd \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

# Composer 2 — copied from the official image so it is always current.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/99-facebook-clone.ini

WORKDIR /var/www/html

# Install PHP dependencies first so the layer is cached between builds.
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-autoloader

COPY . .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache/data,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Development variant: same image plus Xdebug for step debugging.
# -----------------------------------------------------------------------------
FROM base AS development

USER root

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=develop,debug"      >> /usr/local/etc/php/conf.d/99-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/99-xdebug.ini \
    && echo "xdebug.start_with_request=yes"  >> /usr/local/etc/php/conf.d/99-xdebug.ini \
    && apk del .build-deps

USER www-data
