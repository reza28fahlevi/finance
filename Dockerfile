# syntax=docker/dockerfile:1.7
#
# Finance Shafa — image produksi (multi-stage)
#   vendor : dependensi Composer (tanpa dev)
#   assets : build aset Vite/Tailwind dengan Node (tidak ikut ke image akhir)
#   app    : PHP-FPM 8.4 (tanpa Node) — target default
#   web    : Nginx yang melayani /public dan meneruskan PHP ke service app
#
# Build:  docker compose build
# Manual: docker build --target app -t finance-shafa-app .
#         docker build --target web -t finance-shafa-web .

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

############################
# 1. Composer dependencies #
############################
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --no-interaction --no-progress --no-scripts \
        --prefer-dist --optimize-autoloader --ignore-platform-reqs

#########################
# 2. Front-end (Vite)   #
#########################
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
# Tailwind memindai view pagination Laravel (lihat @source di resources/css/app.css)
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

#########################
# 3. PHP-FPM application #
#########################
FROM php:${PHP_VERSION}-fpm-alpine AS app

# Ekstensi PHP yang dibutuhkan (pdo_mysql, bcmath untuk aritmetika uang, intl untuk format tanggal ID).
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl \
    && apk add --no-cache mysql-client tzdata \
    && cp /usr/share/zoneinfo/Asia/Jakarta /etc/localtime && echo "Asia/Jakarta" > /etc/timezone

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-finance.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-finance.conf

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN set -eux; \
    rm -rf node_modules tests template_mockup .env .env.* storage/logs/*.log; \
    mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    php artisan package:discover --ansi; \
    chmod +x docker/entrypoint.sh

USER www-data

EXPOSE 9000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php-fpm"]

#########################
# 4. Nginx (web)        #
#########################
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
EXPOSE 80
