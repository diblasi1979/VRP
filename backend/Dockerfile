FROM php:8.4-cli-alpine

RUN apk add --no-cache curl curl-dev oniguruma-dev sqlite sqlite-dev $PHPIZE_DEPS \
    && docker-php-ext-install curl mbstring pdo_sqlite \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

EXPOSE 8000