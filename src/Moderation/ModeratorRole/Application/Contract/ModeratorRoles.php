<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Application\Contract;

/**
 * Si alguien modera, y con qué nivel
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Existe porque **el token no lleva roles**
 * ([`decision:0007`](../../../../../docs/decisions/0007-jwt-sessions.md)): meterlos ahí haría
 * que un permiso retirado siguiera vigente hasta que caducara, y en el
 * backoffice eso son quince minutos leyendo obra inédita y datos personales
 * de cualquiera. Así que el rol se resuelve **en cada petición**, y quien lo
 * resuelve —`User`, al autenticar— tiene que poder preguntarlo.
 *
 * Pregunta y devuelve un dato. No concede nada: el rol se concede desde el
 * backoffice o por consola, que es lo que hace que el registro de auditoría
 * signifique algo (`FEAT-MOD-012` `RN-6`).
 */
interface ModeratorRoles
{
    public function of(string $userId): ?ModeratorStanding;
}
