# Image PHP commune aux deux API (PHP pur + Symfony).
#  - php-fpm pour Symfony (derrière nginx)
#  - le serveur intégré "php -S" pour l'API en PHP pur
FROM php:8.4-fpm-alpine

# Installeur d'extensions officiel de la communauté (évite pecl/docker-php-ext-* à la main)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql intl opcache zip xdebug \
    && apk add --no-cache bash git unzip postgresql-client

# Composer = l'équivalent de NuGet + dotnet restore
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Les conteneurs tournent avec l'UID/GID de l'hôte (voir Makefile) :
# HOME et le cache Composer doivent donc être accessibles à n'importe quel utilisateur.
ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
