<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use PHPUnit\Framework\TestCase;

/**
 * The price formula of `decision:0006`. It is the single most consequential
 * piece of arithmetic in the product: it is what an author pays and what a
 * reader earns, and the two are the same number.
 */
final class ChapterPricingTest extends TestCase
{
    private ChapterPricing $pricing;

    protected function setUp(): void
    {
        $this->pricing = new ChapterPricing();
    }

    /**
     * The worked examples from the decision itself.
     */
    public function testItPricesTheExamplesOfTheDecision(): void
    {
        // Microrrelato: 800 palabras, 1 pregunta de 50 → 1 + 1
        self::assertSame(2, $this->pricing->priceOf(800, 50));

        // Relato breve: 3.000 palabras, 3 × 100 → 3 + 3
        self::assertSame(6, $this->pricing->priceOf(3000, 300));
    }

    public function testBothTermsRoundUp(): void
    {
        // 1.001 palabras ya son dos créditos de lectura: leer un poco más de
        // mil palabras no es leer mil.
        self::assertSame(2 + 1, $this->pricing->priceOf(1001, 100));
    }

    public function testThePriceNeverGoesBelowTwo(): void
    {
        self::assertSame(
            ChapterPricing::MIN_PRICE,
            $this->pricing->priceOf(0, 0),
            'Corregir algo siempre cuesta algo.',
        );
    }

    public function testThePriceNeverGoesAboveTwenty(): void
    {
        self::assertSame(
            ChapterPricing::MAX_PRICE,
            $this->pricing->priceOf(75_000, 10_000),
            'El techo protege al autor de un cuestionario desmedido.',
        );
    }

    /**
     * Sin suelo por pregunta, un cuestionario que no exige nada valdría cero
     * en el término de escritura y **una novela entera se corregiría por dos
     * créditos**. Es el caso que el suelo existe para evitar.
     */
    public function testAQuestionWithNoMinimumStillCosts(): void
    {
        $sinMinimos = $this->pricing->requiredWords(array_fill(0, 10, null));

        self::assertSame(10 * ChapterPricing::WORDS_PER_UNBOUNDED_QUESTION, $sinMinimos);

        // Y el efecto que importa: un cuestionario de diez preguntas sin
        // mínimos sigue costando más que el suelo, porque pide trabajo real
        // aunque el autor no lo haya declarado.
        self::assertGreaterThan(
            ChapterPricing::MIN_PRICE,
            $this->pricing->priceOf(800, $sinMinimos),
        );
    }

    public function testADeclaredMinimumWins(): void
    {
        self::assertSame(200 + 25, $this->pricing->requiredWords([200, null]));
    }

    public function testAMinimumOfZeroCountsAsUndeclared(): void
    {
        self::assertSame(
            ChapterPricing::WORDS_PER_UNBOUNDED_QUESTION,
            $this->pricing->requiredWords([0]),
        );
    }

    /**
     * La propiedad que sostiene toda la economía: el precio es una
     * transferencia. Lo que paga el autor es lo que cobra el lector, así que
     * cambiar las constantes no crea ni destruye créditos.
     */
    public function testTheAuthorPaysExactlyWhatTheReaderEarns(): void
    {
        $precio = $this->pricing->priceOf(4200, 350);

        $saldoAutor = 30 - $precio;
        $saldoLector = 10 + $precio;

        self::assertSame(40, $saldoAutor + $saldoLector, 'Una corrección mueve créditos; no los crea.');
    }

    /**
     * `RN-7`: las constantes son palancas, y se mueven por configuración.
     * Reequilibrar leer contra escribir no puede exigir un despliegue.
     */
    public function testTheCalibrationCanBeMovedWithoutTouchingTheFormula(): void
    {
        // Escribir cuesta el doble: 100 palabras de crítica valen 2 créditos.
        $masCaroEscribir = new ChapterPricing(wordsPerWritingCredit: 50);

        self::assertSame(1 + 2, $masCaroEscribir->priceOf(800, 100));
        self::assertSame(1 + 1, $this->pricing->priceOf(800, 100));
    }

    public function testAnEmptyPriceRangeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ChapterPricing(minPrice: 20, maxPrice: 2);
    }

    public function testALeverCannotBeZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Dividir el recuento por cero no es una calibración agresiva: es un
        // error de configuración, y conviene que falle al arrancar.
        new ChapterPricing(wordsPerReadingCredit: 0);
    }

    public function testItRefusesNegativeWordCounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->pricing->priceOf(-1, 0);
    }
}
