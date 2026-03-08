FROM alpine:3.23

RUN apk add --no-cache \
    php84 php84-fpm php84-pdo php84-pdo_mysql php84-zip \
    php84-pecl-redis php84-phar php84-iconv php84-mbstring \
    php84-tokenizer php84-xml php84-dom php84-ctype \
    php84-session php84-fileinfo php84-curl php84-openssl \
    git unzip

# nginx コンテナから app:9000 へ接続するため listen アドレスを変更
RUN sed -i 's/127.0.0.1:9000/0.0.0.0:9000/' /etc/php84/php-fpm.d/www.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY app/ .
RUN mkdir -p var/tmp var/log && chmod -R 777 var

EXPOSE 9000
CMD ["php-fpm84", "-F"]
