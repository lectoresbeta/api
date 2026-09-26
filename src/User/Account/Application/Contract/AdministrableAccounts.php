<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Las cuentas, para quien las administra
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **La puerta más ancha que publica `User`**, y la única que reparte
 * direcciones de correo hacia una pantalla. Existe porque `Moderation` no es
 * dueño de los usuarios —lo es este contexto— y un backoffice que no puede
 * encontrar una cuenta no sirve de nada (`FEAT-MOD-005`).
 *
 * Sigue siendo un contrato y no una ventana: **pregunta, no manda**. No
 * sanciona, no activa, no elimina. Quien quiera cambiar el estado de una
 * cuenta lo pide por un hecho, y lo aplica este contexto, que es el dueño.
 *
 * El filtro de estado incluye a propósito las cuentas **eliminadas**: son
 * exactamente las que un moderador puede necesitar encontrar para explicar
 * por qué algo desapareció. Lo que devuelven es su identificador y su estado,
 * no lo que fueron: están anonimizadas.
 */
interface AdministrableAccounts
{
    /**
     * @param string|null $term  correo, nombre de usuario o nombre; nulo lista todas
     * @param int<1, 100> $limit
     *
     * @return list<AdministrableAccount>
     */
    public function search(?string $term, int $limit = 25, int $offset = 0): array;

    public function ofId(string $userId): ?AdministrableAccount;
}
