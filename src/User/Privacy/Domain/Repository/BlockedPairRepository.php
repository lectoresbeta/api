<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Entity\BlockedPair;

/**
 * La copia de los bloqueos que `User` necesita para responder qué autor
 * acepta comentarios de quién (`FEAT-COM-034`).
 *
 * Una sola pregunta, y sin dirección: **¿hay bloqueo entre estos dos?**
 */
interface BlockedPairRepository
{
    public function exists(UserId $one, UserId $other): bool;

    public function between(UserId $one, UserId $other): ?BlockedPair;

    public function save(BlockedPair $pair): void;

    public function remove(BlockedPair $pair): void;
}
