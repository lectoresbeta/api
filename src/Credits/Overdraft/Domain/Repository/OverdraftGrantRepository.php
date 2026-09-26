<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftGrant;

interface OverdraftGrantRepository
{
    public function save(OverdraftGrant $grant): void;

    /**
     * Cuántos descubiertos se han concedido **en toda la plataforma** en ese
     * periodo, que es como se cuenta el cupo (`FEAT-CRD-019` `RN-2`).
     *
     * Global y no por autor: el cupo es el techo de emisión de la semana, no
     * un límite personal. Lo personal lo responde `hasGrantFor()`.
     */
    public function countInPeriod(string $quotaPeriod): int;

    /**
     * Si a ese autor ya se le concedió alguno, alguna vez.
     *
     * Una sola vez por persona (`RN-1`, `RN-6b`): si no volvió con uno no va
     * a volver con dos, y cada intento es una hora de trabajo de un lector
     * que quizá nadie lea.
     */
    public function hasGrantFor(UserId $authorId): bool;

    /**
     * La elegibilidad viva de ese autor, si la tiene: la que hace que uno de
     * sus capítulos admita una corrección que no puede pagar.
     */
    public function usableOf(UserId $authorId, \DateTimeImmutable $moment): ?OverdraftGrant;

    /**
     * Los que ese autor tiene sin saldar, para cerrarlos cuando repone.
     *
     * @return list<OverdraftGrant>
     */
    public function unsettledOf(UserId $authorId): array;

    /**
     * Cuántos se han concedido y cuántos se han saldado (`FEAT-CRD-012`).
     *
     * **Un descubierto que nunca se salda es emisión pura**, así que esta
     * proporción es lo que dice si el gancho de reactivación está funcionando
     * o regalando créditos.
     *
     * @return array{granted: int, settled: int}
     */
    public function recovery(): array;
}
