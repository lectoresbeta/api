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
use LectoresBeta\User\Onboarding\Application\Command\CompleteOnboarding;
use LectoresBeta\User\Onboarding\Domain\Event\OnboardingCompleted;
use LectoresBeta\User\Onboarding\Domain\Exception\OnboardingStepOutOfOrder;

/**
 * Cerrar el onboarding (`FEAT-COM-016` `RN-2`, `RN-8`).
 *
 * El paso 3 es **el único opcional**: se puede terminar sin seguir a nadie, y
 * también sin que el paso llegue a mostrarse —en una plataforma recién
 * lanzada no hay autores que sugerir, y ese es justamente el momento en que
 * todo el mundo pasa por aquí—. Por eso esta operación no comprueba
 * suscripciones: lo que cierra el onboarding es decir que se ha terminado.
 *
 * Lo que sí comprueba es el **orden**: sin nombre y sin géneros no se está en
 * el paso 3, se está saltando los anteriores.
 *
 * **Es idempotente.** Volver a llamarla sobre algo ya terminado deja el mundo
 * igual y no publica un segundo hecho: quien la repite suele ser un doble
 * clic o un reintento.
 */
final readonly class CompleteOnboardingHandler
{
    public function __construct(
        private UserRepository $users,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CompleteOnboarding $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->users->ofId($userId);

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        if ($user->onboardingStatus()->isCompleted()) {
            return;
        }

        if (OnboardingStatus::SUGGESTIONS_PENDING !== $user->onboardingStatus()) {
            throw OnboardingStepOutOfOrder::expecting(OnboardingStatus::PROFILE_PENDING === $user->onboardingStatus() ? 'profile' : 'genres');
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($user, $now): void {
            $user->completeOnboarding($now);
            $this->users->save($user);
        });

        $this->events->publish(new OnboardingCompleted(EventId::generate(), $userId, $now));
    }
}
