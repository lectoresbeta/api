#!/bin/sh
set -e

# var/ tiene que existir y ser escribible antes de que arranque nada: el
# kernel escribe la caché ahí en el primer arranque.
mkdir -p var/cache var/log
chown -R www-data:www-data var 2>/dev/null || true

# En desarrollo el código viene por volumen, así que vendor/ puede no existir
# todavía la primera vez que se levanta el contenedor.
if [ "${APP_ENV}" = "dev" ] && [ ! -f vendor/autoload_runtime.php ]; then
    echo "vendor/ vacío: instalando dependencias…"
    composer install --no-interaction --prefer-dist
fi

exec "$@"
