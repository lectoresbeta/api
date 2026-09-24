<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Application\DTO\EditableProfile;
use LectoresBeta\User\Profile\Domain\Exception\ProfileNotFound;

/**
 * La cuenta de quien pregunta, y su perfil tal y como lo edita.
 *
 * Existe para que el `GET` y el `PATCH` carguen y pinten lo mismo: dos
 * lecturas distintas del propio perfil son dos formas de que una acabe
 * enseñando un campo que la otra no.
 */
final readonly class MyProfile
{
    public function __construct(private UserRepository $users)
    {
    }

    public function of(string $userId): User
    {
        try {
            $user = $this->users->ofId(UserId::fromString($userId));
        } catch (InvalidValue) {
            throw ProfileNotFound::create();
        }

        // Una sesión viva cuya cuenta ya no está: el token sobrevive al
        // borrado hasta que caduca, y aquí no hay nada que editar.
        if (null === $user || $user->status()->isDeleted()) {
            throw ProfileNotFound::create();
        }

        return $user;
    }

    public static function asView(User $user, \DateTimeImmutable $now): EditableProfile
    {
        return new EditableProfile(
            $user->id()->value(),
            $user->username()->value(),
            $user->name()?->value(),
            $user->description(),
            $user->avatarUrl(),
            $user->coverUrl(),
            $user->usernameChangeableOn($now),
        );
    }
}
