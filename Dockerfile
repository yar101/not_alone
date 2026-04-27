# ── Stage 1: build frontend assets ───────────────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 2: production image ─────────────────────────────────────────────────
FROM php:8.4-fpm

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libxml2-dev \
        curl \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_pgsql \
        pgsql \
        pcntl \
        bcmath \
        intl \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies (production only)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application source
COPY . .

# Copy built frontend assets from stage 1
COPY --from=assets /app/public/build ./public/build

# Finalize composer autoloader
RUN composer dump-autoload --optimize

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Configs
COPY docker/nginx-prod.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/php-fpm-prod.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
