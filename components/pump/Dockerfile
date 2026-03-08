FROM alpine:3.23

RUN apk add --no-cache \
    php84 php84-pdo php84-pdo_mysql php84-zip \
    php84-pecl-redis php84-pecl-swoole \
    php84-phar php84-iconv php84-mbstring \
    php84-tokenizer php84-xml php84-dom php84-ctype \
    php84-openssl php84-curl php84-simplexml \
    git unzip

# $_ENV に環境変数を展開する（Alpine の PHP デフォルトは GPCS のため E を追加）
RUN echo "variables_order = EGPCS" >> /etc/php84/php.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY app/ .

CMD ["php", "bin/pump.php"]
