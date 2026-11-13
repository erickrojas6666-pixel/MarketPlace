# ---------- 1. Dependencias PHP ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --no-scripts \
    --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ---------- 2. Assets (Vite + Vue + PWA) ----------
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm ci --ignore-scripts && npm run build

# ---------- 3. Imagen final ----------
FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
      libpq-dev libgmp-dev libzip-dev libicu-dev unzip \
 && docker-php-ext-install pdo_pgsql pdo_mysql bcmath gmp intl zip opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && printf '<Directory /var/www/html/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/public/sw.js ./public/sw.js

RUN mkdir -p storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

RUN sed -i --follow-symlinks '/Alias \/icons\//d' /etc/apache2/mods-enabled/alias.conf

EXPOSE 10000
ENTRYPOINT ["/entrypoint.sh"]
