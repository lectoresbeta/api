<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\DTO;

/**
 * Un miembro del grupo, tal y como se pinta en la lista (`FEAT-RDG-007`).
 *
 * Su tarjeta de perfil y nada más: ni correo, ni actividad, ni nada que el
 * autor no viera ya al añadirlo.
 */
final readonly class GroupMemberCard
{
    public function __construct(
        public string $userId,
        public ?string $username,
        public ?string $name,
        public ?string $avatarUrl,
        public \DateTimeImmutable $addedAt,
    ) {
    }
}
