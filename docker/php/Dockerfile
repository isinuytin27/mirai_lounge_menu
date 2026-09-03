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
# Composer ставим через БАЗОВЫЙ php:8.2 (в нём есть pdo_pgsql/fileinfo), а не из apk —
# apk-пакет composer тянет отдельный php8.5 без наших расширений и падает на platform-req.
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer install --no-dev --no-interaction --optimize-autoloader \
    && rm /usr/local/bin/composer

COPY scripts/generate-vapid.php ./scripts/generate-vapid.php

# Кэш Twig (prod пишет в var/cache/twig). Базовый каталог root-овый, а php-fpm бежит
# под www-data — создаём var/ заранее и отдаём его www-data, иначе Twig падает
# «Unable to create the cache directory».
RUN mkdir -p var/cache/twig && chown -R www-data:www-data var

# Остальной код монтируется volume’ами; vendor и scripts/generate-vapid.php — в образе.
