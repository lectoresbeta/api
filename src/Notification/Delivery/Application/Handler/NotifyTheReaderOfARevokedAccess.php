<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\BetaReaderAccessRevoked;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Ya no puedes leer esta obra» (`FEAT-NOT-001`).
 *
 * **El aviso que saldó la deuda de dos fichas.** Quien estaba corrigiendo
 * pierde ese trabajo cuando le retiran el acceso —por un bloqueo o por
 * decisión del autor— y hasta ahora nada se lo decía: el texto sencillamente
 * dejaba de poder entregarse. Descubrirlo sin explicación es mucho peor que
 * que te lo digan.
 *
 * **No dice por qué**, y es deliberado: el hecho no distingue los tres
 * caminos, y contar que ha habido un bloqueo sería avisar de un bloqueo, que
 * es justo lo que `FEAT-COM-034` `RN-2` evita. Dice lo que la persona
 * necesita saber para no seguir escribiendo en balde.
 *
 * Tampoco se descarta cuando el propio lector lo provocó —descartar su
 * borrador—: ahí el hecho no trae más actor que él, y `Notify` no puede
 * distinguirlo. Es el precio de que los tres caminos publiquen lo mismo, y se
 * asume: un aviso de más es mejor que el silencio que había.
 */
final readonly class NotifyTheReaderOfARevokedAccess
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(BetaReaderAccessRevoked $event): void
    {
        $this->notify->deliver(
            $event->readerId,
            NotificationKind::BETA_READER_ACCESS_REVOKED,
            $event->eventId(),
            $this->notify->work($event->workId),
        );
    }
}
