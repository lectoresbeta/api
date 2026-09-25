<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Event\CorrectionStarted;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;

/**
 * Alguien ha empezado a corregir, así que este texto ya no se sobrescribe sin
 * dejar copia (`FEAT-WRK-005` `RN-3`).
 *
 * **Leer no basta; corregir sí.** Un capítulo publicado lo abre cualquiera, y
 * versionar en cada lectura llenaría la tabla de copias que nadie va a
 * consultar. Lo que hay que conservar es el texto sobre el que alguien está
 * escribiendo una corrección por la que el autor va a pagar.
 *
 * Idempotente sin necesitar registro: marcar una versión ya marcada no cambia
 * nada, porque la fecha se guarda solo la primera vez.
 *
 * Queda una ventana, y la ficha la nombra (`W-24`): entre que alguien pulsa
 * «empezar corrección» y llega este hecho, el autor puede guardar. La
 * alternativa —preguntarle a `Feedback` en cada guardado— convertiría cada
 * edición en una llamada síncrona a otro contexto.
 */
final readonly class MarkChapterVersionRead
{
    public function __construct(
        private ChapterRepository $chapters,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(CorrectionStarted $event): void
    {
        try {
            $chapter = $this->chapters->ofId(ChapterId::fromString($event->chapterId));
        } catch (InvalidValue) {
            return;
        }

        if (null === $chapter || $chapter->needsArchivingBeforeEditing()) {
            return;
        }

        $chapter->markVersionRead($event->occurredAt());

        $this->session->execute(function () use ($chapter): void {
            $this->chapters->save($chapter);
        });
    }
}
