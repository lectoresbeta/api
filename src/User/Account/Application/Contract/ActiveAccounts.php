<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Cuáles de estas cuentas están **activas**
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Activa significa `ACTIVE` y nada más: ni sin activar, ni suspendida, ni
 * expulsada, ni eliminada. Es una pregunta distinta de la que responde
 * `VisibleProfiles`, y por eso son dos contratos y no uno con un parámetro.
 * «¿Puedo ver el perfil de esta persona?» y «¿tiene esta persona una cuenta
 * en uso?» se separan justo donde importa: los comentarios de alguien
 * suspendido se siguen leyendo —su firma es visible— y en cambio no hay que
 * proponer seguirlo.
 *
 * Existe para `FEAT-COM-016` `RN-7`: una cuenta sin activar no puede
 * publicar, así que sugerir seguirla no lleva a ninguna parte.
 *
 * **Por lotes, nunca de una en una.** Sirve para filtrar una lista entera, y
 * una llamada por fila sería un N+1 escondido detrás de un contrato.
 */
interface ActiveAccounts
{
    /**
     * @param list<string> $userIds
     *
     * @return list<string> los que están activos, en cualquier orden; **quien
     *                      no lo esté sencillamente no está**
     */
    public function activeAmong(array $userIds): array;
}
