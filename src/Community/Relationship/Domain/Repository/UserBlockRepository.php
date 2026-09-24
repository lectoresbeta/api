<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Domain\Repository;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Domain\Entity\UserBlock;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface UserBlockRepository
{
    public function between(MemberId $blockerId, MemberId $blockedId): ?UserBlock;

    /**
     * Si hay bloqueo **en cualquiera de las dos direcciones**.
     *
     * Es la pregunta que hay que hacer casi siempre, y la razón está en
     * `FEAT-COM-034`: el bloqueo es unilateral en la intención y
     * **bidireccional en el efecto**. Preguntar solo por una dirección deja
     * abierta la puerta de vuelta.
     */
    public function existsBetween(MemberId $one, MemberId $other): bool;

    /**
     * A quién ha bloqueado esta persona, de lo más reciente a lo más
     * antiguo, con una fila de más para saber si hay página siguiente.
     *
     * @return list<UserBlock>
     */
    public function blockedBy(MemberId $blockerId, ?Cursor $after, int $limit): array;

    public function save(UserBlock $block): void;

    public function remove(UserBlock $block): void;
}
