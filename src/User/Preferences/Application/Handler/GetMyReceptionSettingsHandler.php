<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\DTO\ReceptionSettingsView;
use LectoresBeta\User\Preferences\Application\Query\GetMyReceptionSettings;
use LectoresBeta\User\Preferences\Application\Service\StoredReceptionSettings;

/**
 * Los ajustes de recepción de quien pregunta (`FEAT-USR-011`).
 *
 * Quien nunca los ha tocado recibe **los valores por defecto**, no un hueco:
 * la pantalla tiene que poder pintar dos interruptores el primer día.
 */
final readonly class GetMyReceptionSettingsHandler
{
    public function __construct(private StoredReceptionSettings $stored)
    {
    }

    public function __invoke(GetMyReceptionSettings $query): ReceptionSettingsView
    {
        $settings = $this->stored->of(UserId::fromString($query->userId));

        return new ReceptionSettingsView(
            $settings->acceptsBetaReaderInvitations(),
            $settings->acceptsWritingBuddyProposals(),
            $settings->updatedAt(),
        );
    }
}
