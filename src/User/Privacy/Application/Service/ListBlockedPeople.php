<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Contract\BlockedPeople;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;

/**
 * El lado de `User` de «¿con quién tengo un bloqueo?» (`FEAT-COM-034`).
 *
 * Una consulta, las dos columnas, y **los dos sentidos en la misma respuesta**:
 * quien pregunta no tiene que saber quién bloqueó a quién, porque el efecto
 * es el mismo en los dos casos.
 *
 * Un identificador ilegible responde la lista vacía en vez de fallar. Quien
 * pregunta es un catálogo pintando una página, y tumbárselo por eso sería
 * convertir un dato mal formado en una pantalla en blanco.
 */
final readonly class ListBlockedPeople implements BlockedPeople
{
    public function __construct(private BlockedPairRepository $pairs)
    {
    }

    public function blockedWith(string $userId): array
    {
        try {
            return $this->pairs->everyoneBlockedWith(UserId::fromString($userId));
        } catch (InvalidValue) {
            return [];
        }
    }
}
