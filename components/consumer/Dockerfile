FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install --configureoptions 'enable-sockets="no" enable-openssl="no" enable-mysqlnd="no" enable-swoole-curl="no" enable-cares="no" enable-brotli="no" enable-zstd="no" enable-swoole-pgsql="no" with-swoole-odbc="no" with-swoole-oracle="no" enable-swoole-sqlite="no" enable-swoole-thread="no" enable-iouring="no"' swoole \
    && docker-php-ext-enable swoole \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-swoole

COPY app/ .
RUN mkdir -p var/tmp var/log && chmod -R 777 var

CMD ["php", "bin/worker.php"]
