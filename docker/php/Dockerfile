# syntax=docker/dockerfile:1

# Imagen de la aplicación. Tres etapas: lo común, desarrollo y producción.
#
# PHP 8.4 no es opcional: Doctrine 3 usa los objetos perezosos nativos del
# lenguaje y varios componentes de Symfony del bloqueo piden >=8.4.1.
ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm AS base

# install-php-extensions resuelve las dependencias de sistema de cada
# extensión. Compilar amqp e intl a mano en la imagen oficial es varias
# capas de apt-get que hay que mantener.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        amqp \
        intl \
        opcache \
        pdo_pgsql \
        zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

COPY docker/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/php/fpm.d/ /usr/local/etc/php-fpm.d/
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/php/install-deps.sh /usr/local/bin/install-deps
RUN chmod +x /usr/local/bin/entrypoint /usr/local/bin/install-deps

WORKDIR /app

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]


FROM base AS dev

# El código llega por volumen, así que todo lo que el contenedor escriba
# —vendor/, var/, lo que corrija php-cs-fixer— aparece en el disco de quien
# desarrolla. Si lo escribe root, en Linux acaba con un checkout que no puede
# borrar sin sudo.
#
# La solución es que `www-data` dentro de la imagen tenga el UID de fuera.
# `-o` permite que el identificador ya esté ocupado: da igual que coincida con
# otro usuario del sistema, lo que importa es el número.
ARG UID=1000
ARG GID=1000

ENV APP_ENV=dev \
    APP_DEBUG=1 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data \
    && mkdir -p /tmp/composer \
    && chown -R www-data:www-data /tmp/composer

# En desarrollo el código llega por volumen: la imagen solo trae el runtime.


FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Las dependencias primero y el código después: mientras composer.lock no
# cambie, reconstruir la imagen no vuelve a descargar nada.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-autoloader --no-interaction --prefer-dist

COPY . .

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var
