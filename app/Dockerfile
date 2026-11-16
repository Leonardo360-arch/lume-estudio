FROM php:8.3-apache

WORKDIR /var/www/html

RUN apt-get update \
  && apt-get install -y --no-install-recommends default-mysql-client unzip libzip-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
  && docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
  && docker-php-ext-install pdo pdo_mysql zip mbstring gd \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/lume.ini

COPY composer.json composer.lock .env.example ./
COPY src/helpers.php ./src/helpers.php
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .

RUN mkdir -p storage/albums \
  && chown -R www-data:www-data storage \
  && composer dump-autoload --no-dev --classmap-authoritative --no-scripts

EXPOSE 80
