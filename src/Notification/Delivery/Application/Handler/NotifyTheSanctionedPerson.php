<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\SanctionImposed;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Se ha impuesto una restricción sobre tu cuenta» (`FEAT-MOD-006` `RN-4`).
 *
 * **Era el hueco que más dolía del inventario**: hasta ahora se sancionaba a
 * alguien y esa persona lo descubría chocándose, sin saber qué le habían
 * hecho, por qué, ni hasta cuándo.
 *
 * Es `MODERATION_ALERT`, que es **operativo**: no pasa por preferencias, ni
 * siquiera por el interruptor general. No es negociable por la misma razón
 * que no lo es el aviso de contraseña cambiada — quien pudiera apagarlo se
 * quedaría sin enterarse de lo que le está pasando a su cuenta. Es además lo
 * que hace que una sanción corrija en vez de solo castigar.
 *
 * Lleva **tipo, motivo y hasta cuándo**, que es lo que el hecho ya traía.
 * `expiresAt` nulo significa indefinida, y el cliente tiene que decirlo con
 * esas palabras: «indefinida» y «hasta el martes» no se leen igual.
 *
 * No lleva quién la impuso. Un moderador no es un actor social, y nombrarlo
 * convertiría una decisión de la plataforma en un asunto entre dos personas.
 */
final readonly class NotifyTheSanctionedPerson
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(SanctionImposed $event): void
    {
        $this->notify->deliver(
            $event->userId,
            NotificationKind::MODERATION_ALERT,
            $event->eventId(),
            [
                'sanctionId' => $event->sanctionId,
                'sanctionType' => $event->type,
                'reason' => $event->reason,
                'expiresAt' => $event->expiresAt?->format(\DATE_ATOM),
            ],
        );
    }
}
