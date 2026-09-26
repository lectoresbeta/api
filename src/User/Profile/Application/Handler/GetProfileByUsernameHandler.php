<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Application\DTO\PublicProfile;
use LectoresBeta\User\Profile\Application\Query\GetProfileByUsername;
use LectoresBeta\User\Profile\Application\Service\VisibleProfile;
use LectoresBeta\User\Profile\Domain\Enum\ProfileResolution;
use LectoresBeta\User\Profile\Domain\Exception\ProfileNotFound;
use LectoresBeta\User\Profile\Domain\Repository\UsernameAliasRepository;

/**
 * El perfil de alguien, por nombre de usuario (`FEAT-USR-035`).
 *
 * **Primero los nombres en uso y solo después los alias** (`RN-1`). Es lo que
 * mantiene vivos los enlaces compartidos después de un cambio de nombre, sin
 * que un alias pueda tapar a quien tenga ese nombre ahora.
 *
 * Un alias solo resuelve si está **vigente** (`RN-2`), y esa es la regla
 * crítica: comprobar que la fila existe en lugar de que siga en plazo haría
 * que un enlace caducado funcionase durante días, que es justo lo que el mes
 * de reserva pretende acotar. El agregado lo sabe responder, así que aquí no
 * se compara ninguna fecha a mano.
 *
 * Los alias de cuentas eliminadas **bloquean el nombre y nunca resuelven**
 * (`RN-9`): no hay perfil al que llevar.
 */
final readonly class GetProfileByUsernameHandler
{
    public function __construct(
        private UserRepository $users,
        private UsernameAliasRepository $aliases,
        private VisibleProfile $profile,
        private Clock $clock,
    ) {
    }

    public function __invoke(GetProfileByUsername $query): PublicProfile
    {
        try {
            $username = Username::fromString($query->username);
        } catch (InvalidValue) {
            // Un nombre que ni siquiera puede existir no es un error de
            // validación: es un perfil que no está.
            throw ProfileNotFound::create();
        }

        $user = $this->users->ofUsername($username);

        if (null !== $user) {
            return $this->profile->of($user, $query->viewerId, ProfileResolution::USERNAME, $username->value());
        }

        return $this->profile->of(
            $this->behindTheAlias($username),
            $query->viewerId,
            ProfileResolution::ALIAS,
            $username->value(),
        );
    }

    private function behindTheAlias(Username $username): User
    {
        $alias = $this->aliases->ofUsername($username);

        // Vigente, de un cambio de nombre y con titular: las tres cosas las
        // responde el agregado de una vez, que es lo que evita comparar aquí
        // una fecha y olvidarse del motivo.
        if (null === $alias || !$alias->resolvesToProfileAt($this->clock->now())) {
            throw ProfileNotFound::create();
        }

        $owner = $alias->userId();
        $user = null === $owner ? null : $this->users->ofId($owner);

        if (null === $user) {
            throw ProfileNotFound::create();
        }

        return $user;
    }
}
