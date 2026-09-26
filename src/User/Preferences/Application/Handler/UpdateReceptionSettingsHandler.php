<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\Command\UpdateReceptionSettings;
use LectoresBeta\User\Preferences\Application\DTO\ReceptionSettingsView;
use LectoresBeta\User\Preferences\Application\Service\StoredReceptionSettings;
use LectoresBeta\User\Preferences\Domain\Repository\UserReceptionSettingsRepository;

/**
 * Cambiar qué propuestas se admiten (`FEAT-USR-011`).
 *
 * **Un campo ausente no cambia nada.** La pantalla tiene dos interruptores y
 * se mueve uno cada vez; exigir los dos en cada petición haría que mover uno
 * pisara el otro con lo que el cliente tuviera cargado, que es como se
 * pierden ajustes sin que nadie lo note.
 *
 * Cerrar una puerta **no deshace lo que ya llegó**: una invitación pendiente
 * sigue esperando respuesta. Es la misma decisión que en `FEAT-USR-010` con
 * las conversaciones abiertas, y por la misma razón — el ajuste dice quién
 * puede proponer a partir de ahora, no borra lo que alguien ya propuso de
 * buena fe.
 */
final readonly class UpdateReceptionSettingsHandler
{
    public function __construct(
        private StoredReceptionSettings $stored,
        private UserReceptionSettingsRepository $settings,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateReceptionSettings $command): ReceptionSettingsView
    {
        $settings = $this->stored->of(UserId::fromString($command->userId));

        $settings->change(
            $command->betaReaderInvitations ?? $settings->acceptsBetaReaderInvitations(),
            $command->writingBuddyProposals ?? $settings->acceptsWritingBuddyProposals(),
            $this->clock->now(),
        );

        $this->session->execute(function () use ($settings): void {
            $this->settings->save($settings);
        });

        return new ReceptionSettingsView(
            $settings->acceptsBetaReaderInvitations(),
            $settings->acceptsWritingBuddyProposals(),
            $settings->updatedAt(),
        );
    }
}
