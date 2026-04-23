FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-install pdo pdo_mysql intl zip opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html