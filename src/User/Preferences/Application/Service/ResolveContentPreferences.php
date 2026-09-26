<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\Contract\ReaderContentPreferences;
use LectoresBeta\User\Preferences\Domain\Enum\ContentWarning;
use LectoresBeta\User\Preferences\Domain\Repository\ContentPreferencesRepository;

/**
 * `User`'s side of the content-preferences contract.
 *
 * An identifier that is not one answers «nothing excluded» rather than
 * failing: the caller is building a catalogue page, and the safe reading of a
 * nonsensical reader is the unfiltered list they would get without a session
 * — age is a separate rule and filters on its own (`FEAT-USR-044`).
 */
final readonly class ResolveContentPreferences implements ReaderContentPreferences
{
    public function __construct(private ContentPreferencesRepository $preferences)
    {
    }

    public function excludedBy(string $userId): array
    {
        try {
            $reader = UserId::fromString($userId);
        } catch (InvalidValue) {
            return [];
        }

        return array_map(
            static fn (ContentWarning $warning): string => $warning->value,
            $this->preferences->excludedBy($reader),
        );
    }
}
