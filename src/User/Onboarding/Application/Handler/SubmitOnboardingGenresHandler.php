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
use LectoresBeta\User\Onboarding\Domain\Exception\OnboardingAlreadyCompleted;
use LectoresBeta\User\Onboarding\Domain\Exception\OnboardingStepOutOfOrder;
use LectoresBeta\User\Profile\Application\Service\GenreSelection;
use LectoresBeta\User\Profile\Domain\Event\LiteraryPreferencesUpdated;
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
 * never disagree about what somebody likes. The rules about what makes a
 * valid selection live with it, in `GenreSelection`, for the same reason.
 */
final readonly class SubmitOnboardingGenresHandler
{
    public function __construct(
        private UserRepository $users,
        private GenreSelection $selection,
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

        // Nobody has a selection yet at this point, so nothing is kept: a
        // retired genre cannot be chosen here at all.
        $codes = $this->selection->validated($command->genreCodes);

        $now = $this->clock->now();

        $this->session->execute(function () use ($user, $userId, $codes, $now): void {
            $this->preferences->replaceAllOf($userId, $codes, $now);

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
}
