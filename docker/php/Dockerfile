FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    libzip-dev \
    curl-dev \
    sqlite-dev \
    postgresql-dev \
    && docker-php-ext-install -j$(nproc) \
    zip \
    curl \
    opcache \
    pdo_sqlite \
    pdo_pgsql

RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'upload_max_filesize=16M'; \
    echo 'post_max_size=16M'; \
    echo 'allow_url_fopen=On'; \
    } > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/mirailounge

COPY composer.json composer.lock ./
RUN apk add --no-cache --virtual .composer-deps composer \
    && composer install --no-dev --no-interaction --optimize-autoloader \
    && apk del .composer-deps

COPY scripts/generate-vapid.php ./scripts/generate-vapid.php

# Остальной код монтируется volume’ами; vendor и scripts/generate-vapid.php — в образе.
