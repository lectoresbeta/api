<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Work;

use LectoresBeta\Work\Catalog\Domain\Service\CatalogueScore;
use PHPUnit\Framework\TestCase;

/**
 * El orden del catálogo (`decision:0008`). Lo que se comprueba aquí no son
 * cifras concretas sino las tres propiedades que justifican la fórmula.
 */
final class CatalogueScoreTest extends TestCase
{
    private CatalogueScore $score;

    protected function setUp(): void
    {
        $this->score = new CatalogueScore();
    }

    public function testAnUnattendedChapterOutranksAnAttendedOne(): void
    {
        $desatendida = $this->score->of(60, 6, 0, 0.0);
        $atendida = $this->score->of(60, 6, 5, 0.0);

        self::assertGreaterThan($atendida, $desatendida);
    }

    public function testAnAuthorWhoCanPayMoreOutranksOneWhoCanBarelyPayOnce(): void
    {
        $conSaldo = $this->score->of(60, 6, 0, 0.0);
        $justo = $this->score->of(6, 6, 0, 0.0);

        self::assertGreaterThan($justo, $conSaldo);
    }

    public function testSomethingOpenedTodayOutranksSomethingOpenedMonthsAgo(): void
    {
        $reciente = $this->score->of(60, 6, 0, 0.0);
        $vieja = $this->score->of(60, 6, 0, 12.0);

        self::assertGreaterThan($vieja, $reciente);
    }

    /**
     * El tope de capacidad impide que un autor con mucho saldo acapare el
     * catálogo: a partir de diez correcciones pagables, tener más no sube.
     */
    public function testCapacityIsCapped(): void
    {
        $diez = $this->score->of(60, 6, 0, 0.0);
        $mil = $this->score->of(6000, 6, 0, 0.0);

        self::assertSame($diez, $mil);
        self::assertSame(CatalogueScore::MAX_CAPACITY, $diez);
    }

    /**
     * La propiedad que hace que el sistema se reequilibre solo: **aparecer
     * arriba gasta lo que te puso ahí**. Cada corrección recibida cuesta
     * saldo y sube el contador, así que los dos primeros factores bajan a la
     * vez.
     */
    public function testBeingCorrectedLowersTheScoreTwice(): void
    {
        $antes = $this->score->of(60, 6, 0, 0.0);
        $despues = $this->score->of(54, 6, 1, 0.0);

        self::assertLessThan($antes / 2, $despues);
    }

    public function testAnAuthorInDebtScoresZero(): void
    {
        self::assertSame(0.0, $this->score->of(-4, 6, 0, 0.0));
    }

    public function testAPriceIsNeverZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->score->of(10, 0, 0, 0.0);
    }
}
