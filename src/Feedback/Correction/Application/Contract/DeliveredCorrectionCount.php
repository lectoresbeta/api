<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Contract;

/**
 * Cuántas correcciones ha entregado alguien
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Para el contador «Correcciones» del perfil (`FEAT-USR-028`). **Cuenta solo
 * las entregadas**: un borrador a medias no es trabajo hecho, y contarlo
 * convertiría el contador en una cifra que sube y baja sola.
 *
 * Una cifra y nunca la lista, y aquí la diferencia importa especialmente:
 * `FEAT-USR-014` `U-17` decide a propósito que el **contador** de
 * correcciones sea público y la **lista** no. Un contrato que devolviera las
 * correcciones haría esa decisión imposible de mantener.
 */
interface DeliveredCorrectionCount
{
    public function ofReader(string $readerId): int;
}
