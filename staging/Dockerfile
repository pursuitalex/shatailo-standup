# PHP 7.4 — безпечно для WordPress 6.0.12 + WooCommerce 7.5.2 (менше deprecation-помилок, ніж 8.x)
FROM php:7.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
      unzip default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" mysqli gd zip \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/zz-staging.ini
