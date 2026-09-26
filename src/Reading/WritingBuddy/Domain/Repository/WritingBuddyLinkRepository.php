<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Domain\Repository;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Domain\Entity\WritingBuddyLink;
use LectoresBeta\Reading\WritingBuddy\Domain\ValueObject\WritingBuddyLinkId;

interface WritingBuddyLinkRepository
{
    public function ofId(WritingBuddyLinkId $id): ?WritingBuddyLink;

    /**
     * El vínculo **vivo** entre esos dos —propuesto o aceptado—, en el orden
     * que sea.
     *
     * El par se guarda ordenado, así que `(A,B)` y `(B,A)` encuentran la
     * misma fila: no hay dos vínculos paralelos con la misma persona.
     */
    public function liveBetween(ReaderId $one, ReaderId $other): ?WritingBuddyLink;

    /**
     * Los de una persona, del más reciente al más antiguo.
     *
     * Los resueltos no salen: un vínculo rechazado hace dos meses no es una
     * fila que nadie quiera volver a ver, y un histórico de rechazos sería
     * una lista de desaires con acuse de recibo.
     *
     * @return list<WritingBuddyLink> con una fila de más para saber si hay
     *                                página siguiente
     */
    public function liveOf(ReaderId $reader, int $limit, int $offset): array;

    public function save(WritingBuddyLink $link): void;
}
