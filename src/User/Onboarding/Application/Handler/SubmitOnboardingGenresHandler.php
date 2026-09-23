<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Enum\OnboardingStatus;
use LectoresBeta\User\Account\Domain\Exception\UserNotFound;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Onboarding\Application\Command\SubmitOnboardingGenres;
use LectoresBeta\User\Onboarding\Domain\Exception\NotEnoughGenres;
use LectoresBeta\User\Onboarding\Domain\Exception\OnboardingAlreadyCompleted;
use LectoresBeta\User\Onboarding\Domain\Exception\OnboardingStepOutOfOrder;
use LectoresBeta\User\Onboarding\Domain\Exception\UnknownGenre;
use LectoresBeta\User\Profile\Domain\Entity\LiteraryPreference;
use LectoresBeta\User\Profile\Domain\Event\LiteraryPreferencesUpdated;
use LectoresBeta\User\Profile\Domain\Repository\GenreRepository;
use LectoresBeta\User\Profile\Domain\Repository\LiteraryPreferenceRepository;

/**
 * Step 2 of the onboarding: at least three genres (`FEAT-USR-023`).
 *
 * The selection **replaces** whatever was there rather than adding to it.
 * Choosing genres is picking a set, and somebody who takes one off the list
 * expects it gone.
 *
 * It is stored in the same place as the editable literary preferences
 * (`RN-6`): one place, not two, so the onboarding and the settings screen can
 * never disagree about what somebody likes.
 */
final readonly class SubmitOnboardingGenresHandler
{
    public const MINIMUM = 3;

    public function __construct(
        private UserRepository $users,
        private GenreRepository $genres,
        private LiteraryPreferenceRepository $preferences,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SubmitOnboardingGenres $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->users->ofId($userId);

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        if ($user->onboardingStatus()->isCompleted()) {
            throw OnboardingAlreadyCompleted::create();
        }

        if (OnboardingStatus::PROFILE_PENDING === $user->onboardingStatus()) {
            // The age gate needs the date of birth from step 1 before this
            // step can decide what to show anybody.
            throw OnboardingStepOutOfOrder::expecting('profile');
        }

        $codes = self::normalise($command->genreCodes);

        if (\count($codes) < self::MINIMUM) {
            throw NotEnoughGenres::atLeast(self::MINIMUM);
        }

        $unknown = $this->genres->unknownAmong($codes);

        if ([] !== $unknown) {
            throw UnknownGenre::among($unknown);
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($user, $userId, $codes, $now): void {
            $this->preferences->removeAllOf($userId);

            foreach ($codes as $code) {
                $this->preferences->add(new LiteraryPreference($userId, $code, $now));
            }

            $user->completeGenresStep($now);
            $this->users->save($user);
        });

        $this->events->publish(new LiteraryPreferencesUpdated(
            EventId::generate(),
            $userId,
            $codes,
            $now,
        ));
    }

    /**
     * Upper-cased and deduplicated.
     *
     * Duplicates are normalised away rather than refused: sending the same
     * genre twice is a client bug, not a decision by the person, and the set
     * they meant is unambiguous.
     *
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private static function normalise(array $codes): array
    {
        return array_values(array_unique(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            array_filter($codes, static fn (string $code): bool => '' !== trim($code)),
        )));
    }
}
