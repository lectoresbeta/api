<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\WorkPublished;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\AuthorFollowerRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * «Alguien a quien sigues ha publicado algo» (`FEAT-NOT-004`).
 *
 * **Es lo que hace que seguir signifique algo.** Hasta aquí, seguir a un
 * autor cambiaba lo que salía en el muro y nada más: quien no entrara ese día
 * se perdía la publicación sin enterarse nunca.
 *
 * Es el único aviso que se reparte **a muchos a la vez**, y de ahí las dos
 * cautelas:
 *
 * - **por páginas**. Un autor con miles de seguidores no cabe en memoria, y
 *   el día que no quepa no es el día de descubrirlo;
 * - **una entrega por destinatario**. Cada uno tiene sus preferencias y su
 *   propia fila, así que silenciar esto es cosa de cada cual y no del autor.
 *
 * La idempotencia sale sola del índice único `(destinatario, tipo, evento)`:
 * si el reparto se corta a la mitad y RabbitMQ reentrega, los que ya tenían
 * su aviso no reciben otro.
 */
final readonly class NotifyTheFollowersOfANewWork
{
    /**
     * Cuántos seguidores se traen por vuelta.
     *
     * Ni uno —serían tantas consultas como seguidores— ni todos. Doscientos
     * es una consulta barata y un lote que cabe holgadamente en memoria.
     */
    private const BATCH = 200;

    public function __construct(
        private AuthorFollowerRepository $followers,
        private Notify $notify,
    ) {
    }

    public function __invoke(WorkPublished $event): void
    {
        try {
            $author = RecipientId::fromString($event->authorId);
        } catch (InvalidValue) {
            return;
        }

        // Quién es el autor se resuelve **una vez**, no una por seguidor: es
        // el mismo para todos, y preguntarlo dentro del bucle convertiría un
        // reparto en una consulta por persona avisada.
        $who = [
            ...$this->notify->actor($event->authorId),
            'workId' => $event->workId,
            // El título del hecho, no el de ahora: el aviso describe algo que
            // pasó (`RN-6`).
            'workTitle' => $event->title,
        ];

        $after = null;

        do {
            $batch = $this->followers->followersOf($author, $after, self::BATCH);

            foreach ($batch as $followerId) {
                $this->notify->deliver(
                    $followerId,
                    NotificationKind::SUBSCRIBED_AUTHOR_PUBLISHED,
                    $event->eventId(),
                    $who,
                    actorId: $event->authorId,
                );
            }

            $after = [] === $batch ? null : $batch[\count($batch) - 1];
        } while (self::BATCH === \count($batch));
    }
}
