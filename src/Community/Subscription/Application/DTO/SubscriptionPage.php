<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\DTO;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

/**
 * Una página de personas y dónde empieza la siguiente (`FEAT-COM-027`).
 *
 * **Puede traer menos filas que el límite pedido sin que eso signifique que
 * se acabó** (`RN-5`): el filtro de privacidad se aplica después de paginar,
 * así que quien no sea visible deja un hueco. Lo que decide si hay más es
 * `nextCursor`, nunca cuántas filas llegaron.
 *
 * Sin total, y no por pereza: contar aquí sería contar **lo filtrado**, una
 * cifra distinta para cada visitante. Los contadores del perfil son otra
 * funcionalidad (`FEAT-USR-028`).
 */
final readonly class SubscriptionPage
{
    /**
     * @param list<array{profile: DirectoryEntry, subscribedAt: \DateTimeImmutable}> $people
     */
    public function __construct(
        public array $people,
        public ?string $nextCursor,
    ) {
    }
}
