# syntax=docker/dockerfile:1

# Pinned hardened base images (Alpine 3.24 / LTS patch releases).
ARG NODE_VERSION=24.20.0-alpine3.24
ARG PHP_VERSION=8.5.8-fpm-alpine3.24
ARG COMPOSER_VERSION=2.10.3

FROM composer/composer:${COMPOSER_VERSION} AS composer

FROM php:${PHP_VERSION} AS vendor

RUN apk add --no-cache \
        $PHPIZE_DEPS \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apk del --purge $PHPIZE_DEPS \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
    && composer clear-cache \
    && apk del --purge git \
    && rm -rf /root/.composer

FROM node:${NODE_VERSION} AS frontend

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci --ignore-scripts \
    && npm cache clean --force

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
COPY --from=vendor /app/vendor ./vendor

RUN npm run build \
    && rm -rf node_modules vendor

FROM php:${PHP_VERSION} AS runtime

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        $PHPIZE_DEPS \
        nginx \
        supervisor \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && addgroup -g "${GID}" -S app \
    && adduser -u "${UID}" -S -G app app \
    && mkdir -p /var/log/nginx /run/nginx /etc/supervisor/conf.d \
    && chown -R app:app /var/log/nginx /run/nginx \
    && apk del --purge $PHPIZE_DEPS \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/cloudflare.conf /etc/nginx/conf.d/cloudflare.conf
COPY docker/nginx/rate-limit.conf /etc/nginx/conf.d/rate-limit.conf
COPY docker/nginx/proxy-forwarded.conf /etc/nginx/conf.d/proxy-forwarded.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /app/vendor ./vendor
COPY --from=frontend --chown=app:app /app/public ./public

RUN rm -f bootstrap/cache/*.php \
    && mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod +x /usr/local/bin/entrypoint

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD php -r "exit(@fsockopen('127.0.0.1', 8080) ? 0 : 1);" || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["web"]
