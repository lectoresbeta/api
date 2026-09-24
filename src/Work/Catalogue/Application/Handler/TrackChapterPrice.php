<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Handler;

use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Catalogue\Application\Event\ChapterPriceChanged;
use LectoresBeta\Work\Catalogue\Domain\Entity\ChapterSignal;
use LectoresBeta\Work\Catalogue\Domain\Repository\CatalogueSignalRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * La insignia de créditos de la tarjeta (`FEAT-CRD-013`).
 *
 * Idempotente igual que su hermano: la última palabra gana, y un precio con
 * fecha anterior al que ya está guardado se descarta. Cualquiera de los dos
 * hechos puede crear la fila, porque el orden de llegada no está garantizado
 * y esperar al otro dejaría la señal sin escribir.
 *
 * La cifra no se comprueba contra nada. `Work` no sabe calcular precios
 * (`RN-1`) y un catálogo que quisiera validarla tendría que aprender las
 * reglas de crédito, que es exactamente lo que
 * [`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)
 * prohíbe.
 */
final readonly class TrackChapterPrice
{
    public function __construct(
        private CatalogueSignalRepository $signals,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(ChapterPriceChanged $event): void
    {
        $chapterId = ChapterId::fromString($event->chapterId);
        $signal = $this->signals->signalOf($chapterId)
            ?? ChapterSignal::unknown($chapterId, WorkId::fromString($event->workId));

        $signal->price($event->credits, $event->occurredAt());

        $this->session->execute(function () use ($signal): void {
            $this->signals->saveSignal($signal);
        });
    }
}
