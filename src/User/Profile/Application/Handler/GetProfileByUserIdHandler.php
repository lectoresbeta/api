<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Application\DTO\PublicProfile;
use LectoresBeta\User\Profile\Application\Query\GetProfileByUserId;
use LectoresBeta\User\Profile\Application\Service\VisibleProfile;
use LectoresBeta\User\Profile\Domain\Enum\ProfileResolution;
use LectoresBeta\User\Profile\Domain\Exception\ProfileNotFound;

/**
 * El perfil de alguien, por identificador (`FEAT-USR-014`).
 *
 * Un identificador mal escrito responde lo mismo que uno que no existe: ahí
 * no hay nada que distinguir, y distinguirlo solo serviría para que quien
 * prueba identificadores supiera cuándo se acerca.
 */
final readonly class GetProfileByUserIdHandler
{
    public function __construct(
        private UserRepository $users,
        private VisibleProfile $profile,
    ) {
    }

    public function __invoke(GetProfileByUserId $query): PublicProfile
    {
        try {
            $user = $this->users->ofId(UserId::fromString($query->userId));
        } catch (InvalidValue) {
            throw ProfileNotFound::create();
        }

        if (null === $user) {
            throw ProfileNotFound::create();
        }

        return $this->profile->of($user, $query->viewerId, ProfileResolution::USER_ID, $user->username()->value());
    }
}
