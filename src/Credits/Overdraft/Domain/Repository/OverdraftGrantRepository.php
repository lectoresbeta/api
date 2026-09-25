<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftGrant;

interface OverdraftGrantRepository
{
    public function save(OverdraftGrant $grant): void;

    /**
     * Cuántos descubiertos lleva concedidos ese autor en ese periodo, que es
     * como se cuenta el cupo (`FEAT-CRD-019`).
     */
    public function countInPeriod(UserId $authorId, string $quotaPeriod): int;

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
