<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\User\Account\Application\Contract\ActiveAccounts;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;

/**
 * El lado de `User` de «¿está esta cuenta en uso?» (`FEAT-COM-016` `RN-7`).
 *
 * Una consulta para la lista entera, y **la comparación vive aquí**: qué
 * cuenta como activa es de este contexto, y responder el estado en crudo
 * obligaría a quien pregunta a aprender las cinco posibilidades y a decidir
 * cuáles valen — que es exactamente la clase de conocimiento que un contrato
 * existe para no repartir.
 */
final readonly class ListActiveAccounts implements ActiveAccounts
{
    public function __construct(private UserRepository $users)
    {
    }

    public function activeAmong(array $userIds): array
    {
        $wanted = array_values(array_unique(array_filter($userIds, static fn (string $id): bool => '' !== $id)));

        if ([] === $wanted) {
            return [];
        }

        return array_values(array_map(
            static fn (User $user): string => $user->id()->value(),
            array_filter(
                $this->users->ofIds($wanted),
                static fn (User $user): bool => AccountStatus::ACTIVE === $user->status(),
            ),
        ));
    }
}
