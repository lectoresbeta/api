<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Domain\Repository;

use LectoresBeta\Work\Catalogue\Domain\Entity\ChapterCorrectability;
use LectoresBeta\Work\Catalogue\Domain\Entity\DeliveredCorrection;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;

/**
 * Las señales que el catálogo recibe de fuera.
 *
 * Son dos, y las dos son proyecciones: si un capítulo admite correcciones y
 * cuánto trabajo produce enseñarlo (`Credits`), y cuántas correcciones lleva
 * recibidas la obra (`Feedback`). Se reconstruyen reprocesando eventos y no
 * son fuente de verdad de nada.
 */
interface CatalogueSignalRepository
{
    public function saveCorrectability(ChapterCorrectability $chapter): void;

    public function correctabilityOf(ChapterId $chapterId): ?ChapterCorrectability;

    /**
     * Devuelve `false` si esa corrección ya estaba contada, que es lo que
     * hace idempotente la reentrega del mismo hecho.
     */
    public function countDelivered(DeliveredCorrection $correction): bool;
}
