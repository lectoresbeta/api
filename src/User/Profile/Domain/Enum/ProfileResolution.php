<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Enum;

/**
 * Por dónde se ha llegado a un perfil (`FEAT-USR-035` `RN-3`).
 *
 * `ALIAS` es el que importa: significa que el enlace que alguien compartió
 * lleva un nombre que su titular ya no usa, y que el cliente debería
 * **sustituir la URL por la canónica** sin recargar. La API da el dato; la
 * redirección es cosa del frontend, porque aquí se sirven datos y no páginas.
 *
 * `USER_ID` no estaba en `RN-3`, que solo contemplaba los dos nombres. Se
 * añade porque el perfil también se pide por identificador y las dos rutas
 * devuelven lo mismo: decir `USERNAME` cuando nadie ha escrito un nombre
 * sería mentir en un campo que existe para explicar cómo se llegó.
 */
enum ProfileResolution: string
{
    case USER_ID = 'USER_ID';
    case USERNAME = 'USERNAME';
    case ALIAS = 'ALIAS';
}
