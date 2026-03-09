FROM alpine:3.23

RUN apk add --no-cache \
    php84 php84-fpm php84-pdo php84-pdo_mysql php84-zip \
    php84-pecl-redis php84-phar php84-iconv php84-mbstring \
    php84-tokenizer php84-xml php84-dom php84-ctype \
    php84-session php84-fileinfo php84-curl php84-openssl \
    git unzip

# nginx コンテナから app:9000 へ接続するため listen アドレスを変更
RUN sed -i 's/127.0.0.1:9000/0.0.0.0:9000/' /etc/php84/php-fpm.d/www.conf

# $_ENV に環境変数を展開する（Alpine の PHP デフォルトは GPCS のため E を追加）
RUN echo "variables_order = EGPCS" >> /etc/php84/php.ini

# php-fpm ワーカープロセス生成時に環境変数をクリアしないようにする
RUN echo "clear_env = no" >> /etc/php84/php-fpm.d/www.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY app/ .
RUN mkdir -p var/tmp var/log && chmod -R 777 var

EXPOSE 9000
CMD ["php-fpm84", "-F"]
