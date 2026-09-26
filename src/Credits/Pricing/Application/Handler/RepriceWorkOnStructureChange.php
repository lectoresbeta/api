<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Handler;

use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Pricing\Application\Event\ChapterRemoved;
use LectoresBeta\Credits\Pricing\Application\Event\ChaptersReordered;
use LectoresBeta\Credits\Pricing\Application\Service\AnnounceChapterPrices;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Repository\WorkQuestionnaireDemandRepository;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\Service\WorkPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Mover o quitar un capítulo cambia lo que cuesta corregir (`FEAT-WRK-003`).
 *
 * Parece cosmético y no lo es: **el último capítulo es el que responde las
 * preguntas de alcance `LAST_CHAPTER`** (`FEAT-WRK-014` `W-17`), así que
 * reordenar mueve el final de la obra y con él el precio de dos capítulos —el
 * que deja de serlo y el que pasa a serlo—. Sin esto, el anterior seguiría
 * cobrando por una pregunta que ya nadie responderá en él.
 *
 * Los dos hechos llegan con **el orden entero**, así que aplicarlos dos veces
 * da el mismo resultado: es lo único que sobrevive a una cola que entrega al
 * menos una vez. Aun así se deduplica, porque repreciar publica hechos y
 * publicarlos dos veces sí se nota.
 *
 * Quitar un capítulo **no borra su fila de precio**: los movimientos que la
 * citan siguen existiendo y el historial de una persona no puede depender de
 * que nadie borre nada. Lo que se hace es sacarla del cálculo.
 */
final readonly class RepriceWorkOnStructureChange
{
    /** Nombra la **regla**, no la clase (`FEAT-CRD-011` `RN-4`). */
    private const CONSUMER = 'work-structure-pricing';

    public function __construct(
        private ChapterPriceRepository $prices,
        private WorkQuestionnaireDemandRepository $demands,
        private ProcessedEventRepository $processedEvents,
        private WorkPricing $workPricing,
        private RefreshCorrectability $correctability,
        private AnnounceChapterPrices $announcePrices,
        private ChapterPricing $pricing,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function reordered(ChaptersReordered $event): void
    {
        $this->apply($event, $event->workId, $event->order);
    }

    public function removed(ChapterRemoved $event): void
    {
        $this->apply($event, $event->workId, $event->order);
    }

    /**
     * @param list<string> $order
     */
    private function apply(IntegrationEvent $event, string $workId, array $order): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $id = WorkId::fromString($workId);
        $now = $this->clock->now();
        $positions = array_flip($order);

        $known = [];

        foreach ($this->prices->ofWork($id) as $price) {
            $known[$price->chapterId()->value()] = $price;
        }

        $before = AnnounceChapterPrices::snapshot($known);

        $inPlay = [];

        foreach ($order as $chapterId) {
            $price = $known[$chapterId] ?? null;

            if (null === $price) {
                // Un capítulo sin precio todavía: lo estrenará su propio
                // `ChapterContentUpdated`, que llega por su cuenta.
                continue;
            }

            $price->updateContent($positions[$chapterId] + 1, $price->wordCount(), $this->pricing, $now);
            $inPlay[] = $price;
        }

        $announced = [];

        foreach ($this->workPricing->reprice($inPlay, $this->demands->ofWork($id), $now) as $repriced) {
            $announced[$repriced->chapterId()->value()] = $repriced;
        }

        $this->session->execute(function () use ($inPlay, $event, $now): void {
            foreach ($inPlay as $price) {
                $this->prices->save($price);
            }

            $this->processedEvents->markProcessed(
                new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
            );
        });

        $this->announcePrices->of(array_values($announced), $before, $now);

        // Un precio distinto puede poner un capítulo al alcance del saldo del
        // autor, o fuera de él (`FEAT-CRD-009`).
        $this->correctability->forWork($id);
    }
}
