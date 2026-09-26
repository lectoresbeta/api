<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Domain\Repository;

use LectoresBeta\Work\Catalogue\Domain\Entity\ChapterSignal;
use LectoresBeta\Work\Catalogue\Domain\Entity\DeliveredCorrection;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;

/**
 * Las señales que el catálogo recibe de fuera.
 *
 * Son dos, y las dos son proyecciones: lo que `Credits` ha decidido sobre
 * cada capítulo —si admite corrección, cuánto trabajo produce enseñarlo y
 * cuánto se gana corrigiéndolo— y cuántas correcciones lleva recibidas la
 * obra (`Feedback`). Se reconstruyen reprocesando eventos y no son fuente de
 * verdad de nada.
 */
interface CatalogueSignalRepository
{
    public function saveSignal(ChapterSignal $chapter): void;

    public function signalOf(ChapterId $chapterId): ?ChapterSignal;

    /**
     * Devuelve `false` si esa corrección ya estaba contada, que es lo que
     * hace idempotente la reentrega del mismo hecho.
     */
    public function countDelivered(DeliveredCorrection $correction): bool;
}
