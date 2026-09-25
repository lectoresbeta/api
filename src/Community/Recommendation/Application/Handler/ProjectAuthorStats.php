<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Handler;

use LectoresBeta\Community\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Community\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Recommendation\Application\Event\AuthorSubscribed;
use LectoresBeta\Community\Recommendation\Application\Event\AuthorUnsubscribed;
use LectoresBeta\Community\Recommendation\Application\Event\WorkPublished;
use LectoresBeta\Community\Recommendation\Domain\Repository\AuthorStatsRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Los contadores por los que se ordena una sugerencia (`FEAT-COM-016`).
 *
 * **Se mantienen con hechos, no se cuentan al leer.** Un `COUNT` de
 * seguidores y publicaciones por cada tarjeta, en cada carga del onboarding,
 * se degrada justo cuando la plataforma empieza a funcionar; es además el
 * criterio de aceptación que lo dice con todas las letras.
 *
 * Tres hechos y un solo consumidor: los tres tocan la misma fila, y tenerlos
 * juntos deja ver de un vistazo qué la mueve.
 *
 * **Y los tres pasan por el registro de lo ya aplicado**, porque aquí hay un
 * efecto que sumar y no un estado que afirmar. La distinción es la misma que
 * hace `User` con su grafo de seguidores: allí la fila es el par, así que
 * reprocesar el hecho escribe lo mismo; un contador que sube dos veces con el
 * mismo hecho deja a un autor arriba en las sugerencias por una reentrega.
 *
 * El apunte va **dentro de la misma transacción** que el efecto: después,
 * una caída en medio permitiría aplicarlo otra vez; antes, un rollback
 * perdería el efecto y daría el hecho por hecho.
 */
final readonly class ProjectAuthorStats
{
    /**
     * Nombra **la regla**, no la clase que la implementa, para que renombrar
     * una clase no reabra hechos que ya se aplicaron.
     */
    private const CONSUMER = 'author-stats';

    public function __construct(
        private AuthorStatsRepository $stats,
        private ProcessedEventRepository $processed,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function followed(AuthorSubscribed $event): void
    {
        $this->on($event, $event->authorId, function (MemberId $author, \DateTimeImmutable $now): void {
            $stats = $this->stats->of($author, $now);
            $stats->followerGained($now);
            $this->stats->save($stats);
        });
    }

    public function unfollowed(AuthorUnsubscribed $event): void
    {
        $this->on($event, $event->authorId, function (MemberId $author, \DateTimeImmutable $now): void {
            $stats = $this->stats->of($author, $now);
            $stats->followerLost($now);
            $this->stats->save($stats);
        });
    }

    /**
     * Publicar una obra suma a dos sitios: cuántas lleva y **en qué géneros
     * escribe**, que es lo que permite sugerir por afinidad.
     */
    public function published(WorkPublished $event): void
    {
        $this->on($event, $event->authorId, function (MemberId $author, \DateTimeImmutable $now) use ($event): void {
            $stats = $this->stats->of($author, $now);
            $stats->workPublished($now);
            $this->stats->save($stats);

            foreach ($event->genres as $code) {
                $genre = $this->stats->genre($author, $code);
                $genre->workCounted();
                $this->stats->saveGenre($genre);
            }
        });
    }

    /**
     * @param \Closure(MemberId, \DateTimeImmutable): void $project
     */
    private function on(IncomingIntegrationEvent $event, string $authorId, \Closure $project): void
    {
        try {
            $author = MemberId::fromString($authorId);
        } catch (InvalidValue) {
            // Un hecho con un identificador ilegible no se reintenta: se
            // descarta. Reintentarlo lo dejaría dando vueltas para siempre.
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($event, $project, $author, $now): void {
            if ($this->processed->wasProcessed($event->eventId(), self::CONSUMER)) {
                return;
            }

            $project($author, $now);

            $this->processed->markProcessed(new ProcessedEvent(
                $event->eventId(),
                self::CONSUMER,
                $event->eventName(),
                $now,
            ));
        });
    }
}
