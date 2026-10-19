# Local development image only (php artisan serve, docker-compose).
# Production runs on the aaPanel host; see deploy.sh.
FROM php:8.4-cli-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    unzip \
    libxml2-dev \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libzip-dev \
    icu-dev \
    ffmpeg

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install pdo_mysql bcmath gd exif zip pcntl intl opcache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Runtime dirs are gitignored, so a fresh checkout doesn't have them
RUN mkdir -p bootstrap/cache storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/framework/testing storage/logs

# Install Composer dependencies
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Set permissions for Laravel storage and cache directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
