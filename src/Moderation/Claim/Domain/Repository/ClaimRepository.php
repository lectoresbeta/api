<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Repository;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimReason;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;

interface ClaimRepository
{
    public function save(Claim $claim): void;

    public function ofId(ClaimId $id): ?Claim;

    /**
     * La reclamación que esa persona ya presentó sobre ese objeto, si la hay.
     *
     * Es `RN-2`: nadie reclama dos veces lo mismo, y reintentar devuelve la
     * que ya existe en vez de crear otra. Diez denuncias de una persona sobre
     * un texto no son diez señales, son la misma repetida.
     */
    public function of(PartyId $reporterId, ClaimTargetType $targetType, string $targetId): ?Claim;

    /**
     * Cuántas ha presentado desde ese instante, para el cupo de `RN-6`.
     */
    public function countBy(PartyId $reporterId, \DateTimeImmutable $since): int;

    /**
     * @return list<Claim>
     */
    public function by(PartyId $reporterId): array;

    /**
     * La cola de un moderador: lo que sigue abierto **menos aquello en lo que
     * es parte** (`FEAT-MOD-002` `RN-1`).
     *
     * La exclusión se hace aquí y no al decidir a propósito. La ficha lo pide
     * así —«no basta con rechazar la acción, no debe verlas»— y tiene razón:
     * ver el expediente de una reclamación que te señala ya es saber quién te
     * denunció.
     *
     * **De la más antigua a la más reciente, siempre** (`FEAT-MOD-008`
     * `RN-1`). No hay parámetro de ordenación: en una cola cuyo orden elige
     * quien la trabaja, los casos incómodos se hunden, y una reclamación sin
     * resolver es alguien esperando.
     *
     * @param ?ClaimReason     $reason     el motivo por el que se acota
     *                                     (`RN-2`), o `null` para toda la
     *                                     cola
     * @param ?ClaimTargetType $targetType sobre qué clase de cosa
     *
     * @return list<Claim>
     */
    public function openExcludingParty(
        PartyId $moderator,
        int $limit,
        int $offset = 0,
        ?ClaimReason $reason = null,
        ?ClaimTargetType $targetType = null,
    ): array;

    /**
     * Cuántas hay en esa cola, con los mismos filtros (`FEAT-MOD-008`
     * `RN-4`).
     *
     * Es lo que convierte una página en una cola: sin la cifra, quien modera
     * ve veinte expedientes y no sabe si detrás hay cero o mil, que es la
     * única información con la que se decide si hoy hay que pedir ayuda.
     */
    public function countOpenExcludingParty(
        PartyId $moderator,
        ?ClaimReason $reason = null,
        ?ClaimTargetType $targetType = null,
    ): int;

    /**
     * La misma reclamación, con la fila bloqueada hasta que cierre la
     * transacción.
     *
     * Es `RN-8`: tomar y resolver son una sola operación, así que dos
     * moderadores que lleguen a la vez se ordenan aquí y el segundo encuentra
     * un expediente ya cerrado. Sin el bloqueo, los dos leerían `PENDING`,
     * los dos escribirían una decisión y **se publicarían dos hechos
     * contradictorios** sobre el mismo objeto.
     */
    public function lockedById(ClaimId $id): ?Claim;

    /**
     * Las reclamaciones que **apuntan a** esa persona (`FEAT-MOD-005`).
     *
     * La hermana de `by()`, que devuelve las que presentó. La ficha del
     * backoffice enseña las dos porque son dos cosas distintas: quién se
     * queja mucho y de quién se quejan mucho.
     *
     * @return list<Claim>
     */
    public function about(PartyId $subjectId): array;
}
