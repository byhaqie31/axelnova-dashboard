# syntax=docker/dockerfile:1.7
#
# Production image for axelnova-dashboard backend (Laravel 11).
# Default CMD runs nginx + php-fpm via supervisord (web service).
# Override CMD in compose for queue worker:
#   command: ["php", "artisan", "queue:work", "--tries=3", "--sleep=3"]

# ---------- vendor stage ----------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist

COPY . .

RUN mkdir -p bootstrap/cache \
    && composer dump-autoload --optimize --classmap-authoritative \
    && php artisan package:discover --ansi

# ---------- runtime stage ----------
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        bash \
        curl \
        tini \
        nginx \
        supervisor \
        libzip \
        icu-libs \
        oniguruma \
        libpng \
        libjpeg-turbo \
        freetype \
    && apk add --no-cache --virtual .build-deps \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pdo_mysql \
        zip \
        gd \
        opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

WORKDIR /app

COPY --from=vendor --chown=www-data:www-data /app /app

COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf     /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/run /var/log/supervisor /var/lib/nginx/tmp \
    && chown -R www-data:www-data /var/lib/nginx /app/storage /app/bootstrap/cache

EXPOSE 8003

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8003/up || exit 1

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]
