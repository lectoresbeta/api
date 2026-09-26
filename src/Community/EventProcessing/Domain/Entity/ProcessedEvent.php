<?php

declare(strict_types=1);

namespace LectoresBeta\Community\EventProcessing\Domain\Entity;

/**
 * Un hecho que este contexto ya ha aplicado, **por consumidor**.
 *
 * Hace falta aquí por la misma razón que en `Credits`: RabbitMQ no garantiza
 * entrega única, y estas proyecciones **acumulan**. Un contador de obras
 * publicadas que sube dos veces con el mismo hecho no se equivoca por poco:
 * deja a un autor arriba en las sugerencias por una reentrega.
 *
 * La distinción que decide si hace falta o no es la que ya usa `User` para su
 * grafo de seguidores: allí no hay control de duplicados porque **la fila es
 * el par** —un estado que afirmar— y volver a procesar el hecho escribe lo
 * mismo. Aquí hay un efecto que sumar, y eso sí necesita registro.
 *
 * La fila se escribe **dentro de la misma transacción** que el efecto. Si se
 * escribiera después, una caída en medio permitiría aplicarlo otra vez; si
 * antes, un rollback perdería el efecto y daría el hecho por hecho.
 *
 * La clave es `(eventId, consumer)` y no el identificador solo: con el
 * identificador solo, la primera regla que procesara un hecho lo daría por
 * procesado **para todas las demás**.
 *
 * Es una tabla propia y no la de `Credits`: un contexto no lee el registro de
 * otro, y compartirla ataría el purgado de una a las reglas de la otra.
 */
class ProcessedEvent
{
    private string $eventId;

    private string $consumer;

    private string $eventName;

    private \DateTimeImmutable $processedAt;

    public function __construct(string $eventId, string $consumer, string $eventName, \DateTimeImmutable $now)
    {
        $this->eventId = $eventId;
        $this->consumer = $consumer;
        $this->eventName = $eventName;
        $this->processedAt = $now;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }
}
