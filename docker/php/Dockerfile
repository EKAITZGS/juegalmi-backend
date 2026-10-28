# Basado en data/php/Dockerfile del ejemplo de clase dockerSymfonySSL, actualizado para Symfony 7.4 LTS
FROM php:8.4-fpm
ARG TIMEZONE=Europe/Madrid
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && rm -rf /var/lib/apt/lists/* \
    && printf '[PHP]\ndate.timezone = "%s"\n' "$TIMEZONE" > /usr/local/etc/php/conf.d/tzone.ini
COPY php.ini /usr/local/etc/php/conf.d/docker-php-config.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/api
