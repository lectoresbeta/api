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

    /**
     * Cuáles de esos están bloqueados con esta persona, **en cualquiera de
     * los dos sentidos**.
     *
     * Existe para filtrar una página entera de resultados: preguntar par a
     * par sería una consulta por fila, y un bloqueo esconde en los dos
     * sentidos (`FEAT-COM-034`), así que hay que mirar las dos columnas.
     *
     * @param list<string> $otherIds
     *
     * @return list<string> los que hay que esconder
     */
    public function blockedAmong(UserId $one, array $otherIds): array;

    public function between(UserId $one, UserId $other): ?BlockedPair;

    public function save(BlockedPair $pair): void;

    public function remove(BlockedPair $pair): void;
}
