<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\Command\UpdateContentPreferences;
use LectoresBeta\User\Preferences\Domain\Enum\ContentWarning;
use LectoresBeta\User\Preferences\Domain\Exception\UnknownContentWarning;
use LectoresBeta\User\Preferences\Domain\Repository\ContentPreferencesRepository;

/**
 * Elegir qué no ver (`FEAT-USR-043`).
 *
 * **Se comprueba contra el catálogo cerrado** y no se acepta cualquier cosa:
 * una exclusión que no coincide con ninguna etiqueta real no filtra nada y
 * parece que sí, que es la peor manera de fallar en algo que la gente
 * configura precisamente para no llevarse un disgusto. Y el error va en la
 * dirección peligrosa — aceptar una errata enseñaría justo lo que se ha
 * pedido no ver.
 *
 * Se rechaza **entero**: si alguna etiqueta no existe no se guarda nada. Una
 * exclusión a medias deja a alguien creyendo que ha filtrado más de lo que ha
 * filtrado.
 */
final readonly class UpdateContentPreferencesHandler
{
    public function __construct(
        private ContentPreferencesRepository $preferences,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateContentPreferences $command): void
    {
        $wanted = [];
        $unknown = [];

        foreach ($command->excludedWarnings as $code) {
            $code = strtoupper(trim($code));

            if ('' === $code) {
                continue;
            }

            $warning = ContentWarning::tryFrom($code);

            if (null === $warning) {
                $unknown[$code] = $code;

                continue;
            }

            $wanted[$warning->value] = $warning;
        }

        if ([] !== $unknown) {
            throw UnknownContentWarning::of(array_values($unknown));
        }

        $userId = UserId::fromString($command->userId);
        $now = $this->clock->now();

        $this->session->execute(function () use ($userId, $wanted, $now): void {
            $this->preferences->replace($userId, array_values($wanted), $now);
        });
    }
}
