<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Application\Handler;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimReason;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimType;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\ContentReview\Application\Event\ChapterContentUpdated;
use LectoresBeta\Moderation\ContentReview\Application\Port\ContentReviewer;
use LectoresBeta\Moderation\ContentReview\Domain\Entity\ContentReview;
use LectoresBeta\Moderation\ContentReview\Domain\Event\ContentReviewFlagged;
use LectoresBeta\Moderation\ContentReview\Domain\Event\ContentReviewPassed;
use LectoresBeta\Moderation\ContentReview\Domain\Repository\ContentReviewRepository;
use LectoresBeta\Moderation\ContentReview\Domain\ValueObject\ContentReviewId;
use LectoresBeta\Moderation\ContentReview\Domain\ValueObject\ReviewVerdict;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterTexts;

/**
 * Revisar el texto de un capítulo (`FEAT-MOD-011`).
 *
 * **Hoy no marca nada**, y ese es el valor: el paso existe, los estados
 * existen, los hechos existen y las pruebas del camino completo pasan.
 * Sustituir el revisor por uno que lea de verdad no obliga a tocar `Work`, ni
 * el flujo de publicación, ni nada de esto.
 *
 * Tres reglas viven aquí y merecen nombrarse:
 *
 * - **si el revisor falla, el texto se publica igual** (`RN-6`). Es la
 *   decisión contraria a la intuitiva —lo seguro parecería no publicar— y es
 *   la correcta mientras el revisor apruebe todo: bloquear ante una caída
 *   sería impedir publicar por nada. El fallo queda anotado en el veredicto,
 *   que es lo que permitirá revisar esos textos después;
 * - **marcar no es sancionar** (`RN-5`). Se retira el texto y se abre una
 *   reclamación para que lo mire un humano; no se toca la cuenta de nadie. Un
 *   sistema automático que sanciona solo se equivoca en silencio y a escala,
 *   y el afectado no tiene con quién hablar;
 * - **se puede apagar por configuración** (`RN-7`). Apagado no aprueba: no
 *   revisa, y no anota un veredicto que nadie emitió.
 *
 * El texto se pide a `Work` en el momento, por su contrato publicado, y no
 * viaja en el hecho: una cola que persiste, reintenta y aparca mensajes no es
 * sitio para obra inédita.
 */
final readonly class ReviewChapterContent
{
    /**
     * Quién reclama cuando reclama el sistema (`RN-4`).
     *
     * Un identificador fijo y no el de una persona: la reclamación tiene que
     * poder distinguirse de las humanas al leer la cola, y nadie debe cargar
     * en su historial con lo que decidió una máquina.
     */
    public const SYSTEM = '00000000-0000-4000-8000-000000000001';

    public function __construct(
        private ContentReviewer $reviewer,
        private ContentReviewRepository $reviews,
        private ClaimRepository $claims,
        private ChapterTexts $texts,
        private EventPublisher $events,
        private TransactionalSession $session,
        private Clock $clock,
        private bool $enabled,
    ) {
    }

    public function __invoke(ChapterContentUpdated $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $chapter = $this->texts->ofChapter($event->chapterId);

        if (null === $chapter) {
            // El capítulo ya no está: se borró o se bloqueó entre que se
            // publicó el hecho y llegó aquí. No hay nada que revisar.
            return;
        }

        // `RN-9`, y de paso la idempotencia: **un texto se revisa una vez**,
        // y lo que lo identifica es su cifrado. No la versión del capítulo,
        // que puede no cambiar al editarlo; y no el identificador del hecho,
        // porque así una reentrega de la cola no abre una segunda
        // reclamación sobre el mismo capítulo.
        $hash = hash('sha256', $chapter->contentHtml);

        if ($this->reviews->wasReviewed(ClaimTargetType::CHAPTER, $event->chapterId, $hash)) {
            return;
        }

        $verdict = $this->ask($chapter->contentHtml);
        $now = $this->clock->now();

        $review = new ContentReview(
            ContentReviewId::generate(),
            ClaimTargetType::CHAPTER,
            $event->chapterId,
            $verdict->outcome,
            $hash,
            $verdict->mechanism,
            $verdict->version,
            $now,
            $verdict->reason,
        );

        $claim = $verdict->isFlagged() ? $this->claimAbout($event, $verdict, $now) : null;

        $this->session->execute(function () use ($review, $claim): void {
            $this->reviews->save($review);

            if (null !== $claim) {
                $this->claims->save($claim);
            }
        });

        $this->events->publish($verdict->isFlagged()
            ? new ContentReviewFlagged(
                EventId::generate(),
                $event->chapterId,
                $event->workId,
                $event->authorId,
                (string) $verdict->reason,
                $verdict->version,
                $now,
            )
            : new ContentReviewPassed(
                EventId::generate(),
                $event->chapterId,
                $event->workId,
                $verdict->version,
                $now,
            ));
    }

    /**
     * `RN-6`: un revisor que se cae **aprueba, y lo dice**.
     *
     * El veredicto queda con mecanismo `UNAVAILABLE`, que es un dato y no una
     * línea de registro: lo que hace falta después es poder **encontrar** los
     * textos que nadie revisó de verdad, y para eso hay que poder
     * consultarlos. Un log no se consulta, se busca.
     *
     * El detalle del fallo no entra: por qué se cayó una máquina no es un
     * veredicto sobre el texto de nadie.
     */
    private function ask(string $text): ReviewVerdict
    {
        try {
            return $this->reviewer->review($text);
        } catch (\Throwable) {
            return ReviewVerdict::passed('UNAVAILABLE', '0');
        }
    }

    private function claimAbout(ChapterContentUpdated $event, ReviewVerdict $verdict, \DateTimeImmutable $now): ?Claim
    {
        try {
            $subject = PartyId::fromString($event->authorId);
        } catch (InvalidValue) {
            return null;
        }

        return new Claim(
            ClaimId::generate(),
            ClaimType::INAPPROPRIATE_CHAPTER,
            ClaimTargetType::CHAPTER,
            $event->chapterId,
            PartyId::fromString(self::SYSTEM),
            // El motivo del revisor es texto libre y el catálogo es cerrado:
            // lo que se guarda como motivo es «otro», y el detalle va en la
            // descripción, que es donde un moderador lo va a leer.
            ClaimReason::OTHER,
            $now,
            $subject,
            $verdict->reason,
        );
    }
}
