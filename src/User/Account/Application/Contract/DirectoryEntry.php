<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * One person, as a row of a picker (`FEAT-RDG-006`).
 *
 * **A profile card and nothing else.** No email, no date of birth, no account
 * status: whoever asks is drawing one line of a dropdown, and every extra
 * field would be somebody's data crossing a border for no reason.
 *
 * `name` is nullable because it is: somebody who registered and has not
 * finished onboarding has a username and no name yet.
 */
final readonly class DirectoryEntry
{
    public function __construct(
        public string $userId,
        public string $username,
        public ?string $name,
        public ?string $avatarUrl,
    ) {
    }
}
