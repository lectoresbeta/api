<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Repository;

use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionReply;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;

/**
 * **Una respuesta por corrección** (`FEAT-FBK-005` `RN-2`), garantizado por
 * un índice único y no por una comprobación en código: bajo concurrencia, el
 * código no garantiza nada.
 */
interface CorrectionReplyRepository
{
    public function save(CorrectionReply $reply): void;

    public function ofCorrection(CorrectionId $correctionId): ?CorrectionReply;

    /**
     * Las respuestas de varias correcciones de una vez, para pintar una
     * página de una lista sin una consulta por fila.
     *
     * @param list<CorrectionId> $correctionIds
     *
     * @return array<string, CorrectionReply> indexado por identificador de corrección
     */
    public function ofCorrections(array $correctionIds): array;

    public function remove(CorrectionReply $reply): void;
}
