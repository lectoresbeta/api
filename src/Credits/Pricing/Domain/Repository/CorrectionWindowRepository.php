<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Repository;

use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionWindow;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

interface CorrectionWindowRepository
{
    public function save(CorrectionWindow $window): void;

    public function ofWork(WorkId $workId): ?CorrectionWindow;

    /**
     * Las que se conocen de entre estas, **en una sola consulta**: recalcular
     * la corregibilidad de un autor toca todas sus obras a la vez, y una
     * consulta por obra sería un N+1 dentro del propio contexto.
     *
     * Las obras de las que no se sabe nada **no aparecen** en la respuesta.
     * Quien pregunta decide qué significa eso, y significa «no bloquear».
     *
     * @param list<string> $workIds
     *
     * @return array<string, bool>
     */
    public function stateOf(array $workIds): array;
}
