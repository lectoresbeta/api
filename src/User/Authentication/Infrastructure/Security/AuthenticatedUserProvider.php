<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Security;

use LectoresBeta\Moderation\ModeratorRole\Application\Contract\ModeratorRoles;
use LectoresBeta\Moderation\ModeratorRole\Application\Contract\ModeratorStanding;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Turns the `sub` of a token back into somebody.
 *
 * This is where the fifteen-minute window of `decision:0007` is partly
 * closed: a token signed correctly is **not** enough. The account is looked
 * up on every authenticated request, and one that has been deleted or blocked
 * since the token was issued stops authenticating at once — so the window
 * applies to permissions that change, not to accounts that are shut down.
 *
 * @implements UserProviderInterface<AuthenticatedUser>
 */
final readonly class AuthenticatedUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $users,
        private ModeratorRoles $moderators,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $user = $this->users->ofId(UserId::fromString($identifier));
        } catch (InvalidValue) {
            throw new UserNotFoundException();
        }

        if (null === $user || !$user->status()->canAuthenticate()) {
            throw new UserNotFoundException();
        }

        // The stored identifier and not the one that arrived: normalised,
        // and the only one the rest of the system will recognise.
        return new AuthenticatedUser($user->id()->value(), self::rolesOf($user->id()->value(), $this->moderators));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AuthenticatedUser) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return AuthenticatedUser::class === $class;
    }

    /**
     * Los roles se resuelven **en cada petición** y no viajan en el token
     * (`decision:0007`). Meterlos ahí haría que un permiso retirado siguiera
     * vigente hasta que caducara, y en el backoffice eso son quince minutos
     * leyendo obra inédita y datos personales de cualquiera
     * (`FEAT-MOD-004` `RN-4`, `RN-9`).
     *
     * @return list<string>
     */
    private static function rolesOf(string $userId, ModeratorRoles $moderators): array
    {
        $standing = $moderators->of($userId);

        if (null === $standing) {
            return ['ROLE_USER'];
        }

        // Un administrador modera además de administrar: es un nivel por
        // encima y no un rol distinto, así que lleva los dos.
        return ModeratorStanding::ADMIN === $standing->level
            ? ['ROLE_USER', 'ROLE_MODERATOR', 'ROLE_ADMIN']
            : ['ROLE_USER', 'ROLE_MODERATOR'];
    }
}
