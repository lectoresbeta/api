<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Handler;

use LectoresBeta\User\Account\Domain\Exception\UserNotFound;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Onboarding\Application\DTO\OnboardingState;
use LectoresBeta\User\Onboarding\Application\Query\GetOnboardingState;

final readonly class GetOnboardingStateHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(GetOnboardingState $query): OnboardingState
    {
        $user = $this->users->ofId(UserId::fromString($query->userId));

        if (null === $user) {
            throw UserNotFound::withId($query->userId);
        }

        return new OnboardingState(
            $user->onboardingStatus()->value,
            $user->username()->value(),
            $user->onboardingStatus()->isCompleted(),
        );
    }
}
