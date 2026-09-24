<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Messenger;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * Un hecho que llega de la cola, reconstruido **por cada contexto que lo
 * escucha**.
 *
 * Existe por una razón que no se ve hasta que pasa: `Messenger` decodifica un
 * mensaje del transporte en **un** objeto, y aquí un mismo hecho puede tener
 * varios dueños. `CorrectionStarted` es el primero que lo tuvo: `Credits`
 * anota con él el precio y `Reading` concede con él el acceso, cada uno con
 * su propia clase, porque compartirla sería compartir modelo
 * ([`decision:0013`](../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)).
 *
 * En un despliegue con un proceso por contexto esto no haría falta: cada
 * worker tendría su cola, su registro y su única clase. Esta aplicación los
 * ejecuta juntos, así que la decodificación produce todas las
 * reconstrucciones y `DispatchIncomingFacts` las reparte.
 *
 * **No es un `IntegrationEvent`** a propósito: si lo fuera, el enrutado lo
 * mandaría de vuelta a RabbitMQ.
 */
final readonly class IncomingFacts
{
    /**
     * @param list<IncomingIntegrationEvent> $events la misma cosa, contada en los términos de cada contexto
     */
    public function __construct(
        public string $eventName,
        public array $events,
    ) {
    }
}
