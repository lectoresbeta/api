<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\SubmitPublicCorrection;
use LectoresBeta\Feedback\Correction\Application\DTO\PublicCorrectionReceipt;
use LectoresBeta\Feedback\Correction\Application\Service\WriteAnswers;
use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Event\PublicCorrectionSubmitted;
use LectoresBeta\Feedback\Correction\Domain\Exception\PublicCorrectionRefused;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionAnswerRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\Service\AnswerValidator;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Contract\CorrectionBriefs;
use LectoresBeta\Work\PublicLink\Application\Contract\PublicLinkAccess;

/**
 * Corregir por un enlace público, sin cuenta (`FEAT-FBK-008`).
 *
 * **Nada de lo que hace mueve créditos**, y la ausencia es la
 * especificación: `Credits` ni siquiera consume el hecho que publica. Al otro
 * lado no hay cuenta a la que abonar, así que no hay abono; y como el autor
 * no paga, tampoco hay cargo.
 *
 * Eso es lo que convierte esto en la **válvula de seguridad de la economía**.
 * El riesgo mayor del sistema de créditos es el bloqueo —un autor sin saldo
 * no recibe correcciones, y para conseguir saldo necesita textos que
 * corregir—, y este camino lo rompe por fuera: un autor a cero siempre tiene
 * una salida que no depende de nadie más que de su agenda.
 *
 * Dos comprobaciones merecen nombrarse:
 *
 * - **quien tiene sesión no pasa por aquí** (`RN-1`). Sin esa regla, el autor
 *   podría pegar el enlace en su muro y conseguir que usuarios registrados le
 *   corrigiesen gratis: él se ahorraría los créditos y ellos perderían los
 *   suyos;
 * - **el capítulo no se comprueba contra `openForCorrection`**. El enlace
 *   funciona sobre un borrador a propósito ([`FEAT-WRK-010`](../../../../../docs/features/work/FEAT-WRK-010-public-correction-link.md)
 *   `RN-14`): se reparte para conseguir feedback **antes** de publicar, que
 *   es justamente cuando hace falta.
 *
 * El tope del enlace lo publica `Work` y lo cuenta este contexto, que es
 * quien tiene las correcciones. Contarlas también allí sería una segunda
 * copia del número, y una de las dos se quedaría vieja.
 */
final readonly class SubmitPublicCorrectionHandler
{
    public function __construct(
        private PublicLinkAccess $links,
        private CorrectionBriefs $briefs,
        private CorrectionRepository $corrections,
        private CorrectionAnswerRepository $answers,
        private AnswerValidator $validator,
        private WriteAnswers $write,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SubmitPublicCorrection $command): PublicCorrectionReceipt
    {
        if (null !== $command->readerId) {
            throw PublicCorrectionRefused::forSignedInReader($command->chapterId);
        }

        $opening = $this->links->opening($command->token, $command->chapterId);

        if (null === $opening) {
            throw PublicCorrectionRefused::unknownLink();
        }

        if (!$opening->usable) {
            throw PublicCorrectionRefused::linkGone();
        }

        if (!$opening->chapterIsServed) {
            throw PublicCorrectionRefused::chapterNotInThatWork();
        }

        // La casilla la comprueba el servidor y no solo la pantalla: quien
        // envía está aportando un texto propio sin haber aceptado nada.
        if (!$command->acceptedTerms) {
            throw PublicCorrectionRefused::withoutAcceptingTerms();
        }

        if ($this->corrections->publicCountOfLink($opening->publicLinkId) >= $opening->maxCorrections) {
            throw PublicCorrectionRefused::linkIsFull($opening->maxCorrections);
        }

        $brief = $this->briefs->ofChapter($command->chapterId);

        if (null === $brief || $brief->workId !== $opening->workId) {
            throw PublicCorrectionRefused::chapterNotInThatWork();
        }

        if (!$brief->hasQuestions()) {
            throw PublicCorrectionRefused::thereIsNoQuestionnaire();
        }

        $now = $this->clock->now();

        $correction = Correction::startFromPublicLink(
            CorrectionId::generate(),
            WorkId::fromString($brief->workId),
            ChapterId::fromString($brief->chapterId),
            AuthorId::fromString($brief->authorId),
            $brief->questionnaireVersion,
            $now,
            self::label($command->authorLabel),
            $brief->chapterVersion,
            $opening->publicLinkId,
            $now,
        );

        $this->validator->validate($this->write->requirementsOf($brief->questions), $command->answers);

        $written = $this->write->onto($correction, $brief->questions, $command->answers, $now);
        $correction->submit($now);

        $this->session->execute(function () use ($correction, $written): void {
            $this->corrections->save($correction);

            foreach ($written as $answer) {
                $this->answers->save($answer);
            }
        });

        $this->events->publish(new PublicCorrectionSubmitted(
            EventId::generate(),
            $correction->id(),
            $correction->chapterId(),
            $correction->workId(),
            AuthorId::fromString($brief->authorId),
            $correction->authorLabel(),
            $now,
        ));

        return new PublicCorrectionReceipt($correction->id()->value(), $opening->wouldBeWorth);
    }

    /**
     * Una etiqueta, no una identidad: nadie la ha verificado, y al autor le
     * importa distinguir la crítica de su hermana de la de un compañero de
     * taller (`RN-6`).
     */
    private static function label(?string $label): ?string
    {
        $label = null === $label ? '' : trim(strip_tags($label));

        return '' === $label ? null : mb_substr($label, 0, 80);
    }
}
