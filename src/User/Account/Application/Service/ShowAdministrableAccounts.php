<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\AdministrableAccount;
use LectoresBeta\User\Account\Application\Contract\AdministrableAccounts;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * El lado de `User` del backoffice (`FEAT-MOD-005`).
 *
 * Traduce cuentas a fichas y nada más: **no decide quién puede preguntar**.
 * Eso lo decide el rol, en la frontera, y dejarlo aquí sería repartir la
 * misma comprobación por dos sitios.
 */
final readonly class ShowAdministrableAccounts implements AdministrableAccounts
{
    private const MAX = 100;

    public function __construct(private UserRepository $users)
    {
    }

    public function search(?string $term, int $limit = 25, int $offset = 0): array
    {
        /** @var int<1, 100> $capped */
        $capped = max(1, min(self::MAX, $limit));

        return array_map(
            self::asSheet(...),
            $this->users->forAdministration($term, $capped, max(0, $offset)),
        );
    }

    public function ofId(string $userId): ?AdministrableAccount
    {
        try {
            $user = $this->users->ofId(UserId::fromString($userId));
        } catch (InvalidValue) {
            return null;
        }

        return null === $user ? null : self::asSheet($user);
    }

    private static function asSheet(User $user): AdministrableAccount
    {
        return new AdministrableAccount(
            $user->id()->value(),
            $user->username()->value(),
            $user->name()?->value(),
            $user->email()->value(),
            $user->status()->value,
            $user->registeredAt()->format(\DATE_ATOM),
            $user->activatedAt()?->format(\DATE_ATOM),
            $user->lastSignedInAt()?->format(\DATE_ATOM),
            $user->restrictedUntil()?->format(\DATE_ATOM),
        );
    }
}
