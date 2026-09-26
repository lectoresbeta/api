<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Rating\Application\Handler;

use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Feedback\Rating\Application\Command\RateWork;
use LectoresBeta\Feedback\Rating\Domain\Entity\WorkRating;
use LectoresBeta\Feedback\Rating\Domain\Event\WorkRated;
use LectoresBeta\Feedback\Rating\Domain\Exception\WorkNotRatable;
use LectoresBeta\Feedback\Rating\Domain\Repository\WorkRatingRepository;
use LectoresBeta\Feedback\Rating\Domain\ValueObject\WorkRatingId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Valorar una obra de 1 a 5 (`FEAT-FBK-002`).
 *
 * **Solo valora quien la ha corregido**, y es la regla que da sentido al
 * número. Una media abierta a cualquiera que pueda abrir la obra mide cuánta
 * gente pasó por allí; una media de quienes entregaron una corrección dice
 * lo que opina quien de verdad la leyó y se molestó en escribir sobre ella.
 *
 * Es además la única señal de lectura que la plataforma tiene de verdad —no
 * hay seguimiento de lectura (`H-3`)— y la que resiste el fraude que
 * `FEAT-FBK-012` ya teme: inflar una nota exigiría entregar correcciones, que
 * cuesta trabajo y deja rastro.
 *
 * **Un borrador no cuenta.** Lo que acredita haber leído es el trabajo
 * entregado, no el empezado.
 *
 * Una por lector y obra: valorar otra vez **sustituye**. Cambiar de opinión
 * después de leer más capítulos es exactamente lo que se espera, y acumular
 * notas del mismo lector le daría más voz que a los demás.
 */
final readonly class RateWorkHandler
{
    public function __construct(
        private WorkAccessBriefs $works,
        private CorrectionRepository $corrections,
        private WorkRatingRepository $ratings,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RateWork $command): int
    {
        $brief = $this->works->ofWork($command->workId);

        // No existir y no verse responden igual: un `403` sobre una obra
        // inédita ya confirma que está ahí.
        if (null === $brief || !$brief->visibleToOthers) {
            throw WorkNotRatable::create();
        }

        if ($brief->authorId === $command->readerId) {
            throw WorkNotRatable::byItsAuthor();
        }

        try {
            $reader = ReaderId::fromString($command->readerId);
            $workId = WorkId::fromString($command->workId);
        } catch (InvalidValue) {
            throw WorkNotRatable::create();
        }

        if (!$this->corrections->hasDeliveredOn($reader, $workId)) {
            // Se explica, y se puede: quien llega aquí ya ve la obra, así que
            // no se revela nada. Y «corrige un capítulo y podrás valorarla»
            // es una instrucción, no un muro.
            throw WorkNotRatable::withoutHavingCorrectedIt();
        }

        $value = WorkRating::MIN_VALUE <= ($command->rating ?? 0) && ($command->rating ?? 0) <= WorkRating::MAX_VALUE
            ? $command->rating ?? 0
            : throw InvalidValue::because(\sprintf('A rating goes from %d to %d.', WorkRating::MIN_VALUE, WorkRating::MAX_VALUE));

        $now = $this->clock->now();
        $existing = $this->ratings->between($reader, $workId);
        $firstTime = null === $existing;

        $rating = $existing ?? new WorkRating(WorkRatingId::generate(), $workId, $reader, $value, $now);
        $rating->change($value, $now);

        $this->session->execute(function () use ($rating): void {
            $this->ratings->save($rating);
        });

        $this->events->publish(new WorkRated(
            EventId::generate(),
            $command->workId,
            $brief->authorId,
            $command->readerId,
            $value,
            $firstTime,
            $now,
        ));

        return $value;
    }
}
