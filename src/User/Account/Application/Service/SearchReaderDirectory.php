<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use LectoresBeta\User\Account\Application\Contract\ReaderDirectory;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * `User`'s side of the people directory (`FEAT-RDG-006`).
 *
 * The shortest query worth answering is **two characters**. Less than that is
 * not a search, it is the whole table with a filter that matches everybody,
 * and a directory that can be enumerated is a directory somebody will
 * eventually download.
 *
 * The privacy ceiling of `FEAT-USR-038` is applied here and not by whoever
 * asks, which is the point: a caller who has to remember to respect somebody
 * else's privacy is a caller who one day does not. Only `EVERYONE` shows up —
 * `FOLLOWERS` will need a viewer to compare against, and until following
 * exists its follower set is empty, so hiding is the correct answer and not a
 * fallback.
 */
final readonly class SearchReaderDirectory implements ReaderDirectory
{
    public const MIN_QUERY = 2;

    public function __construct(
        private UserRepository $users,
        private UserPrivacySettingsRepository $privacy,
    ) {
    }

    public function search(string $query, int $limit): array
    {
        $trimmed = trim($query);

        if (mb_strlen($trimmed) < self::MIN_QUERY) {
            return [];
        }

        $found = $this->users->matching($trimmed, $limit);

        return array_values(array_map(
            static fn (User $user): DirectoryEntry => new DirectoryEntry(
                $user->id()->value(),
                $user->username()->value(),
                $user->name()?->value(),
                $user->avatarUrl(),
            ),
            array_filter($found, $this->isFindable($this->visibilityOf($found))),
        ));
    }

    /**
     * @param list<User> $found
     *
     * @return array<string, PrivacyAudience>
     */
    private function visibilityOf(array $found): array
    {
        return $this->privacy->profileVisibilityOf(array_map(
            static fn (User $user): string => $user->id()->value(),
            $found,
        ));
    }

    /**
     * Sin fila de ajustes se aplica el valor por defecto explícito (`RN-4`),
     * que es `EVERYONE`. No es «entonces todo vale»: es la misma frase que se
     * habría guardado al crear la cuenta.
     *
     * @param array<string, PrivacyAudience> $visibility
     *
     * @return \Closure(User): bool
     */
    private function isFindable(array $visibility): \Closure
    {
        return static fn (User $user): bool => PrivacyAudience::EVERYONE
            === ($visibility[$user->id()->value()] ?? PrivacyAudience::EVERYONE);
    }
}
