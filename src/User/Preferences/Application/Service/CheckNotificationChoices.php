<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\Contract\NotificationChoices;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationChannel;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationTopic;
use LectoresBeta\User\Preferences\Domain\Repository\NotificationPreferenceRepository;

/**
 * La respuesta a «¿le mando esto?» (`FEAT-USR-039` `RN-1`, `RN-2`, `RN-4`).
 *
 * Tres puertas, en este orden:
 *
 * 1. **el interruptor general**, que suspende todo lo silenciable;
 * 2. **el canal**, porque un aviso puede no existir en él —nadie quiere un
 *    correo por cada mensaje directo— y ofrecerlo donde no existe sería
 *    prometer algo que nunca va a llegar;
 * 3. **la decisión explícita**, y si no hay ninguna, el valor por defecto que
 *    declara el propio tipo.
 *
 * **Un tipo desconocido se permite.** Es la decisión que hace que un aviso
 * nuevo no exija migración ni despliegue del cliente: llega hasta que alguien
 * decida que se puede apagar, y no al revés. Lo contrario significaría que
 * añadir un aviso lo deja mudo hasta que alguien se acuerde del catálogo.
 */
final readonly class CheckNotificationChoices implements NotificationChoices
{
    public function __construct(private NotificationPreferenceRepository $preferences)
    {
    }

    public function allows(string $userId, string $topic, string $channel): bool
    {
        try {
            $owner = UserId::fromString($userId);
        } catch (InvalidValue) {
            return false;
        }

        $wanted = NotificationTopic::tryFrom($topic);
        $through = NotificationChannel::tryFrom($channel);

        if (null === $through) {
            return false;
        }

        if (true === $this->preferences->settingsOf($owner)?->allMuted()) {
            return false;
        }

        if (null === $wanted) {
            return true;
        }

        if (!$wanted->admits($through)) {
            return false;
        }

        return $this->preferences->one($owner, $wanted, $through)?->isEnabled() ?? $wanted->defaultEnabled();
    }
}
