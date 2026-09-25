<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\Contract\ProposalRecipients;
use LectoresBeta\User\Preferences\Domain\Repository\UserReceptionSettingsRepository;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;

/**
 * Las dos puertas de las propuestas (`FEAT-USR-011`).
 *
 * **El bloqueo primero y el ajuste después**, como en `CheckMessageAudience`:
 * el bloqueo es una regla de acceso contra una persona concreta y el ajuste
 * una preferencia general, así que el orden no es de eficiencia sino de
 * significado.
 *
 * Sin fila, los valores por defecto **explícitos**: las dos abiertas. No es
 * lo mismo que «entonces todo vale» — es la misma frase que se guardaría al
 * tocar los ajustes por primera vez, dicha en voz alta.
 *
 * Un identificador que ni siquiera lo es responde `false`, y no una
 * excepción: quien pregunta está a punto de mandarle algo a alguien, y ante
 * la duda lo correcto es no mandarlo.
 */
final readonly class CheckProposalRecipients implements ProposalRecipients
{
    public function __construct(
        private UserReceptionSettingsRepository $settings,
        private BlockedPairRepository $blocks,
    ) {
    }

    public function acceptsBetaReaderInvitations(string $recipientId, string $proposerId): bool
    {
        return $this->answer(
            $recipientId,
            $proposerId,
            static fn (bool $invitations, bool $proposals): bool => $invitations,
        );
    }

    public function acceptsWritingBuddyProposals(string $recipientId, string $proposerId): bool
    {
        return $this->answer(
            $recipientId,
            $proposerId,
            static fn (bool $invitations, bool $proposals): bool => $proposals,
        );
    }

    /**
     * @param callable(bool, bool): bool $door cuál de las dos se pregunta
     */
    private function answer(string $recipientId, string $proposerId, callable $door): bool
    {
        try {
            $recipient = UserId::fromString($recipientId);
            $proposer = UserId::fromString($proposerId);
        } catch (InvalidValue) {
            return false;
        }

        if ($recipient->equals($proposer) || $this->blocks->exists($proposer, $recipient)) {
            return false;
        }

        $settings = $this->settings->ofUser($recipient);

        return null === $settings
            ? $door(true, true)
            : $door($settings->acceptsBetaReaderInvitations(), $settings->acceptsWritingBuddyProposals());
    }
}
