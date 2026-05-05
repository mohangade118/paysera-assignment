FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl \
    libzip-dev libonig-dev libicu-dev \
    librabbitmq-dev libssl-dev \
    autoconf g++ make pkg-config \
    && docker-php-ext-install pdo pdo_mysql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install AMQP extension (required by symfony/amqp-messenger)
RUN pecl install amqp && docker-php-ext-enable amqp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html