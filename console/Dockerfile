FROM php:8.4-cli-alpine

RUN apk add --no-cache curl-dev $PHPIZE_DEPS \
    && docker-php-ext-install curl \
    && apk del $PHPIZE_DEPS

WORKDIR /app

COPY . .

USER www-data

EXPOSE 8081

CMD ["php", "-S", "0.0.0.0:8081", "-t", "public", "public/index.php"]
