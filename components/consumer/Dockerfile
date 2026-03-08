FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY app/ .
RUN mkdir -p var/tmp var/log && chmod -R 777 var

EXPOSE 8081
CMD ["php", "-S", "0.0.0.0:8081", "-t", "public", "public/index.php"]
