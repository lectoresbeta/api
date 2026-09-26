#!/bin/sh
set -e

# Instala las dependencias una sola vez, antes de que arranque nada más.
#
# Es idempotente: si vendor/ ya está al día no hace nada, así que levantar el
# entorno una segunda vez es instantáneo. `composer.lock` más nuevo que el
# autoloader significa que alguien tocó las dependencias.
if [ -f vendor/autoload_runtime.php ] && [ ! composer.lock -nt vendor/autoload_runtime.php ]; then
    echo "Dependencias al día."
else
    echo "Instalando dependencias…"
    composer install --no-interaction --prefer-dist
fi

mkdir -p var/cache var/log config/jwt

# Todo lo que escriba el contenedor tiene que pertenecer a quien desarrolla:
# `www-data` lleva su UID (ver el Dockerfile), así que esto deja el checkout
# utilizable desde fuera.
chown -R www-data:www-data vendor var config/jwt 2>/dev/null || true

echo "Listo."
