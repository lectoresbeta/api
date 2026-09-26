<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Exception\UserNotFound;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\BirthDate;
use LectoresBeta\User\Account\Domain\ValueObject\PersonName;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Onboarding\Application\Command\SubmitOnboardingProfile;
use LectoresBeta\User\Onboarding\Domain\Exception\OnboardingAlreadyCompleted;

/**
 * Step 1 of the onboarding: the public name and the private date of birth
 * (`FEAT-USR-022`).
 *
 * Two fields with **opposite visibility**, which is the thing to keep in mind
 * every time either is touched: the name is how the platform identifies a
 * person everywhere, and the date of birth must never leave this context in a
 * response aimed at anybody else. It is collected because the age gate for
 * `ADULTS_ONLY` runs off it, not to decorate a profile.
 *
 * It works on an account that has not been activated yet: that is the whole
 * point of letting somebody in before they follow the email link
 * (`FEAT-USR-025` `RN-5`).
 */
final readonly class SubmitOnboardingProfileHandler
{
    public function __construct(
        private UserRepository $users,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(SubmitOnboardingProfile $command): void
    {
        $user = $this->users->ofId(UserId::fromString($command->userId));

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        if ($user->onboardingStatus()->isCompleted()) {
            throw OnboardingAlreadyCompleted::create();
        }

        $now = $this->clock->now();
        $name = PersonName::fromString($command->name);
        $birthDate = BirthDate::fromDate(self::parseDate($command->birthDate), $now);

        $this->session->execute(function () use ($user, $name, $birthDate, $now): void {
            $user->completeProfileStep($name, $birthDate, $now);
            $this->users->save($user);
        });
    }

    /**
     * Strict, and deliberately so. `DateTimeImmutable` happily turns
     * `2000-02-30` into the 1st of March, which would store a date the person
     * never typed and silently make them a day older. Round-tripping the
     * formatted value is what catches it.
     */
    private static function parseDate(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw InvalidValue::because('The date of birth must be a real date in YYYY-MM-DD format.');
        }

        return $date;
    }
}
