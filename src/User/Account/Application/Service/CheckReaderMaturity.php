<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\BirthDate;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * `User`'s side of the age contract.
 *
 * Every path that is not a clearly adult account answers `false`: no such
 * user, no date of birth declared, a malformed identifier. The filter it
 * feeds is a legal one and its safe side is «do not show».
 */
final readonly class CheckReaderMaturity implements ReaderMaturity
{
    /**
     * Majority, not the minimum age to sign up: those are different numbers
     * and the second one is still undecided (`OB-15`).
     */
    public const AGE_OF_MAJORITY = 18;

    public function __construct(
        private UserRepository $users,
        private Clock $clock,
    ) {
    }

    public function isOfAge(string $userId): bool
    {
        try {
            $user = $this->users->ofId(UserId::fromString($userId));
        } catch (InvalidValue) {
            return false;
        }

        $birthDate = $user?->birthDate();

        if (null === $birthDate) {
            return false;
        }

        return BirthDate::fromDate($birthDate, $this->clock->now())->ageAt($this->clock->now()) >= self::AGE_OF_MAJORITY;
    }
}
