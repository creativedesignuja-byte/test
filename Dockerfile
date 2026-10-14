# syntax=docker/dockerfile:1
FROM node:22-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-apache-bookworm AS application
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libicu-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-install -j2 intl mbstring pdo_mysql opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY . ./
COPY --from=assets /build/public/build ./public/build
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/rasta.ini
COPY --chmod=755 deploy/entrypoint.sh /usr/local/bin/rasta-entrypoint
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R u=rwX,go=rX /var/www/html \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr LOG_LEVEL=warning
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1/up >/dev/null || exit 1
ENTRYPOINT ["rasta-entrypoint"]
CMD ["apache2-foreground"]
