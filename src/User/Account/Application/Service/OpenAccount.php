<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Event\AccountActivated;
use LectoresBeta\User\Account\Domain\Event\UserRegistered;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Legal\Domain\Entity\LegalAcceptance;
use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;
use LectoresBeta\User\Legal\Domain\Repository\LegalAcceptanceRepository;
use LectoresBeta\User\Legal\Domain\ValueObject\LegalAcceptanceId;
use LectoresBeta\User\Privacy\Application\Service\StartPrivacySettings;

/**
 * Lo que significa que nazca una cuenta, venga por donde venga.
 *
 * Hay **dos puertas de alta** —el correo con contraseña (`FEAT-USR-001`) y
 * Google (`FEAT-USR-002`)— y lo que ocurre detrás tiene que ser lo mismo:
 * se guarda la cuenta, se le dan ajustes de privacidad, se deja constancia de
 * lo que aceptó y se anuncia. Escrito dos veces, el día que aparezca un paso
 * nuevo estará en una de las dos.
 *
 * Los ajustes de privacidad van **en la misma transacción** y no en respuesta
 * a un evento: una cuenta sin ellos, aunque sea un segundo, es una cuenta
 * cuya privacidad alguien tiene que suponer (`FEAT-USR-038` `RN-4`).
 *
 * Y los hechos se publican **después de confirmar**, nunca dentro: un hecho
 * que anuncia una transacción que luego se deshace es un hecho que no
 * ocurrió, y quien lo consumió no puede devolverlo.
 */
final readonly class OpenAccount
{
    public function __construct(
        private UserRepository $users,
        private LegalAcceptanceRepository $acceptances,
        private StartPrivacySettings $privacySettings,
        private TransactionalSession $session,
        private EventPublisher $events,
    ) {
    }

    /**
     * @param array<string, string> $acceptedVersions versión aceptada por tipo, ya comprobada
     */
    public function open(
        User $user,
        array $acceptedVersions,
        ?string $ipAddress,
        \DateTimeImmutable $now,
    ): void {
        $userId = $user->id();

        $this->session->execute(function () use ($user, $userId, $acceptedVersions, $ipAddress, $now): void {
            $this->users->save($user);
            $this->privacySettings->forAccount($userId, $now);

            foreach (LegalDocumentType::cases() as $type) {
                if (!isset($acceptedVersions[$type->value])) {
                    continue;
                }

                $this->acceptances->save(new LegalAcceptance(
                    LegalAcceptanceId::generate(),
                    $userId,
                    $type,
                    $acceptedVersions[$type->value],
                    $now,
                    $ipAddress,
                ));
            }
        });

        $this->events->publish(new UserRegistered(
            EventId::generate(),
            $userId,
            $user->email(),
            $user->username(),
            $now,
        ));

        // Una cuenta que nace ya activada —la de Google, porque el correo
        // viene verificado (`FEAT-USR-002` `RN-8`)— tiene que anunciarlo
        // aquí: es lo que le abona los créditos de bienvenida. Dejárselo a
        // quien llama sería dejar que se olvidara.
        if (AccountStatus::ACTIVE === $user->status()) {
            $this->events->publish(new AccountActivated(
                EventId::generate(),
                $userId,
                $now,
            ));
        }
    }
}
