<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use LectoresBeta\User\Account\Application\Contract\ReaderDirectory;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;

/**
 * `User`'s side of the people directory (`FEAT-RDG-006`).
 *
 * The shortest query worth answering is **two characters**. Less than that is
 * not a search, it is the whole table with a filter that matches everybody,
 * and a directory that can be enumerated is a directory somebody will
 * eventually download.
 *
 * The privacy ceiling of `FEAT-USR-038` belongs in this method. It is not
 * here yet because nobody has privacy settings — their default is `EVERYONE`,
 * so today it would exclude nobody — but **this is where it goes**, so that
 * the day the setting exists no screen has to be reminded to respect it.
 */
final readonly class SearchReaderDirectory implements ReaderDirectory
{
    public const MIN_QUERY = 2;

    public function __construct(private UserRepository $users)
    {
    }

    public function search(string $query, int $limit): array
    {
        $trimmed = trim($query);

        if (mb_strlen($trimmed) < self::MIN_QUERY) {
            return [];
        }

        return array_map(
            static fn (User $user): DirectoryEntry => new DirectoryEntry(
                $user->id()->value(),
                $user->username()->value(),
                $user->name()?->value(),
                $user->avatarUrl(),
            ),
            $this->users->matching($trimmed, $limit),
        );
    }
}
