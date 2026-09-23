#!/bin/sh
set -e

# var/ tiene que existir y ser escribible antes de que arranque nada: el
# kernel escribe la caché ahí en el primer arranque.
#
# Aquí ya no se instalan dependencias. Lo hacía, y como `app` y `worker`
# montan el mismo vendor/ y arrancan a la vez, los dos lanzaban
# `composer install` sobre el mismo directorio: el resultado es un autoloader
# que apunta a paquetes que todavía no estaban. De eso se encarga ahora el
# servicio `deps`, que corre una sola vez y antes que nadie.
mkdir -p var/cache var/log 2>/dev/null || true
chown -R www-data:www-data var 2>/dev/null || true

exec "$@"
