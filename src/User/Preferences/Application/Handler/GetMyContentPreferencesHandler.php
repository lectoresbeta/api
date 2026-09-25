<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\Query\GetMyContentPreferences;
use LectoresBeta\User\Preferences\Domain\Enum\ContentWarning;
use LectoresBeta\User\Preferences\Domain\Repository\ContentPreferencesRepository;

/**
 * Lo que esta persona ha decidido no ver (`FEAT-USR-043`).
 *
 * **Nunca falla por no haber contestado.** Quien no ha excluido nada recibe
 * una lista vacía, que es exactamente lo que significa el estado por defecto:
 * no se filtra nada.
 */
final readonly class GetMyContentPreferencesHandler
{
    public function __construct(private ContentPreferencesRepository $preferences)
    {
    }

    /**
     * @return list<string>
     */
    public function __invoke(GetMyContentPreferences $query): array
    {
        return array_map(
            static fn (ContentWarning $warning): string => $warning->value,
            $this->preferences->excludedBy(UserId::fromString($query->userId)),
        );
    }
}
