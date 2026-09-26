<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * El contrato publicado para emitir el enlace de confirmación de un cambio de
 * correo ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * El mismo patrón que la activación y la recuperación: el secreto se acuña
 * aquí, en el momento de enviar, y nunca viaja por la cola.
 */
interface EmailChangeLinkProvider
{
    /**
     * Devuelve `null` cuando ya no hay nada que mandar: la solicitud no
     * existe, se consumió, o una posterior la anuló. Es el desenlace normal
     * de un evento reentregado, no un error.
     */
    public function issueFor(string $requestId): ?EmailChangeLink;
}
