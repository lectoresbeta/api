<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Work;

use LectoresBeta\Work\Catalogue\Domain\Entity\ChapterSignal;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use PHPUnit\Framework\TestCase;

/**
 * La proyección con la que el catálogo filtra, ordena y pinta la insignia
 * (`FEAT-WRK-012`, `FEAT-CRD-013`).
 *
 * Lo que se defiende aquí es que **la cola no promete orden**: los dos
 * hechos que la alimentan llegan por separado, pueden llegar repetidos y
 * pueden llegar cambiados de sitio.
 */
final class ChapterSignalTest extends TestCase
{
    public function testAChapterNobodyHasSaidAnythingAboutIsNeitherCorrectableNorPriced(): void
    {
        $signal = $this->signal();

        self::assertFalse($signal->isCorrectable());
        self::assertSame(0, $signal->credits());
        self::assertSame(0, $signal->affordableCorrections());
    }

    public function testEachFactOverwritesOnlyItsOwnHalf(): void
    {
        $signal = $this->signal();

        $signal->price(7, $this->at('12:00'));
        $signal->record(true, 3, $this->at('12:01'));

        self::assertSame(7, $signal->credits(), 'La corregibilidad no toca el precio.');
        self::assertTrue($signal->isCorrectable());
        self::assertSame(3, $signal->affordableCorrections());
    }

    /**
     * El caso que obliga a guardar dos fechas: un precio retrasado no puede
     * revivir a una corregibilidad más nueva ni al revés.
     */
    public function testAFactThatArrivesLateDoesNotUndoANewerOne(): void
    {
        $signal = $this->signal();

        $signal->price(7, $this->at('12:05'));
        $signal->price(2, $this->at('12:00'));

        self::assertSame(7, $signal->credits());

        $signal->record(false, 0, $this->at('12:05'));
        $signal->record(true, 9, $this->at('12:00'));

        self::assertFalse($signal->isCorrectable());
        self::assertSame(7, $signal->credits(), 'Y el precio sigue donde estaba.');
    }

    /**
     * La reentrega del mismo hecho es lo normal en una cola que no promete
     * entrega única: aplicarlo dos veces tiene que dar lo mismo.
     */
    public function testApplyingTheSameFactTwiceChangesNothing(): void
    {
        $signal = $this->signal();

        $signal->record(true, 4, $this->at('12:00'));
        $signal->price(6, $this->at('12:00'));
        $signal->record(true, 4, $this->at('12:00'));
        $signal->price(6, $this->at('12:00'));

        self::assertTrue($signal->isCorrectable());
        self::assertSame(4, $signal->affordableCorrections());
        self::assertSame(6, $signal->credits());
    }

    private function signal(): ChapterSignal
    {
        return ChapterSignal::unknown(ChapterId::generate(), WorkId::generate());
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-24 '.$time.':00');
    }
}
