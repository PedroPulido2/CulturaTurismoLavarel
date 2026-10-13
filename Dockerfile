# ---------- Stage 1: assets frontend (Vite + Tailwind 4) ----------
FROM node:20-alpine AS frontend
WORKDIR /app

COPY package.json .npmrc ./
RUN npm install --ignore-scripts

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build


# ---------- Stage 2: dependencias PHP ----------
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
# Instala sin dev y sin scripts (artisan aún no tiene vendor completo)
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist

COPY . .
# Vuelve a dumpear el autoloader optimizado + descubre paquetes
RUN composer dump-autoload --optimize --no-dev \
 && php artisan package:discover --ansi || true
# Copia el build de Vite del stage anterior
COPY --from=frontend /app/public/build ./public/build


# ---------- Stage 3: runtime Apache + PHP 8.4 ----------
FROM php:8.4-apache
WORKDIR /var/www/html

# Extensiones necesarias: Laravel 12 + pgsql (Render) + gd/zip (fotos) + intl/bcmath
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        zip unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_pgsql pgsql \
        mbstring exif pcntl bcmath gd intl zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# php.ini de producción: uploads (fotos max 5MB) + opcache
RUN { \
      echo 'memory_limit=256M'; \
      echo 'upload_max_filesize=10M'; \
      echo 'post_max_size=12M'; \
      echo 'max_execution_time=60'; \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.interned_strings_buffer=8'; \
      echo 'opcache.max_accelerated_files=10000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/production.ini

# Apache: DocumentRoot a /public y permite .htaccess de Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf '<Directory /var/www/html/public>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

COPY --from=vendor /app /var/www/html

# Permisos que Laravel exige
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache \
 && rm -rf public/hot public/storage \
 && php artisan storage:link || true

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack

EXPOSE 80

# Entrypoint: adapta Apache al $PORT de Render, migra tablas stock
# (users/cache/jobs/tokens — el dominio vive en el esquema externo
# culturayturismo y NO lo crean las migraciones), cachea config y arranca.
CMD ["bash", "-lc", "\
set -e; \
PORT=${PORT:-80}; \
sed -i \"s/Listen 80/Listen $PORT/\" /etc/apache2/ports.conf; \
sed -i \"s/:80>/:$PORT>/\" /etc/apache2/sites-enabled/000-default.conf; \
php artisan migrate --force || true; \
php artisan optimize || true; \
exec apache2-foreground"]
