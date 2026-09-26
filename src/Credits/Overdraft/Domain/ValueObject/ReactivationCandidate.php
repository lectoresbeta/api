<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\ValueObject;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

/**
 * Un autor dormido al que merecería la pena volver a traer, y el capítulo
 * concreto por el que se le traería (`FEAT-CRD-019`).
 *
 * Lleva el capítulo y no solo al autor porque la elegibilidad es de **un
 * texto**: lo que se concede es que ese capítulo admita una corrección que su
 * autor no puede pagar, no una línea de crédito abierta.
 */
final readonly class ReactivationCandidate
{
    public function __construct(
        public UserId $authorId,
        public ChapterId $chapterId,
        public WorkId $workId,
        public int $price,
        public int $correctionsGiven,
        public \DateTimeImmutable $lastActiveAt,
    ) {
    }
}
