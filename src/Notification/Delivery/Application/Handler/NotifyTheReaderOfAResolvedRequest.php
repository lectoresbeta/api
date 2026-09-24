<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\AccessRequestRejected;
use LectoresBeta\Notification\Delivery\Application\Event\BetaReaderAccessGranted;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Ya puedes leerla» y «no ha podido ser» (`FEAT-NOT-001`).
 *
 * **Los dos sentidos, y el mismo tipo de aviso**: quien preguntó espera una
 * respuesta, y no recibir ninguna es peor que un no. El `outcome` del payload
 * es lo que distingue la frase, y así la bandeja no tiene que tratar dos
 * tipos para lo que es una sola conversación.
 *
 * Una clase con dos métodos, y no dos clases, porque las dos mitades solo se
 * entienden juntas: si alguien añadiera un tercer desenlace sin mirar el
 * otro, el `outcome` dejaría de cubrir el conjunto.
 */
final readonly class NotifyTheReaderOfAResolvedRequest
{
    public function __construct(private Notify $notify)
    {
    }

    /**
     * Solo cuando el acceso lo concede otra persona.
     *
     * Los otros dos caminos —aceptar una invitación, empezar a corregir una
     * obra pública— los inicia el propio lector, y publican este mismo hecho.
     * Avisarle ahí sería contarle lo que acaba de hacer, que es `RN-3`. No lo
     * filtra `Notify` porque no puede: el actor del hecho es el autor en los
     * tres, y lo que cambia es quién lo puso en marcha.
     */
    public function granted(BetaReaderAccessGranted $event): void
    {
        if ($event->causedByTheReader()) {
            return;
        }

        $this->notify->deliver(
            $event->readerId,
            NotificationKind::ACCESS_REQUEST_RESOLVED,
            $event->eventId(),
            [
                ...$this->notify->actor($event->authorId),
                ...$this->notify->work($event->workId),
                'outcome' => 'GRANTED',
                'accessId' => $event->accessId,
            ],
            actorId: $event->authorId,
        );
    }

    /**
     * El rechazo no lleva quién lo decidió: el hecho no transporta al autor,
     * y a propósito —quien rechaza no tiene por qué dar la cara—. El aviso
     * dice que hubo respuesta y sobre qué obra.
     */
    public function rejected(AccessRequestRejected $event): void
    {
        $this->notify->deliver(
            $event->readerId,
            NotificationKind::ACCESS_REQUEST_RESOLVED,
            $event->eventId(),
            [
                ...$this->notify->work($event->workId),
                'outcome' => 'REJECTED',
                'accessRequestId' => $event->accessRequestId,
            ],
        );
    }
}
