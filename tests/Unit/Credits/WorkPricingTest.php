<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Entity\WorkQuestionnaireDemand;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\Service\WorkPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use PHPUnit\Framework\TestCase;

/**
 * Un cuestionario, muchos precios (`FEAT-CRD-016` `RN-2`).
 *
 * Lo que se comprueba aquí es la regla que justifica que el cuestionario
 * viaje con **dos** cifras de palabras exigidas: el último capítulo responde
 * todas las preguntas, los demás solo las que les aplican.
 */
final class WorkPricingTest extends TestCase
{
    private WorkPricing $pricing;

    private WorkId $work;

    protected function setUp(): void
    {
        $this->pricing = new WorkPricing(new ChapterPricing());
        $this->work = WorkId::generate();
    }

    /**
     * El caso de la maqueta: cuatro preguntas de 100 palabras, y la cuarta
     * —«¿qué te pareció el final?»— solo se responde al final.
     */
    public function testOnlyTheLastChapterPaysForTheQuestionsAboutTheEnding(): void
    {
        $primero = $this->chapter(1, 3000);
        $ultimo = $this->chapter(2, 3000);

        $this->pricing->reprice([$primero, $ultimo], $this->demand(400, 300), $this->now());

        self::assertSame(300, $primero->requiredWords());
        self::assertSame(400, $ultimo->requiredWords());

        // 3 de lectura + 3 de escritura, frente a 3 + 4.
        self::assertSame(6, $primero->price());
        self::assertSame(7, $ultimo->price());
    }

    /**
     * La razón por la que existe `WorkPricing` y no basta con repreciar el
     * capítulo que llega: **aparecer un capítulo nuevo cambia dónde termina
     * la obra**. El que era último deja de serlo, y seguiría cobrando una
     * pregunta que ya nadie responde en él.
     */
    public function testANewChapterDemotesTheOneThatWasLast(): void
    {
        $primero = $this->chapter(1, 3000);
        $segundo = $this->chapter(2, 3000);

        $this->pricing->reprice([$primero, $segundo], $this->demand(400, 300), $this->now());
        self::assertSame(400, $segundo->requiredWords());

        $tercero = $this->chapter(3, 3000);
        $this->pricing->reprice([$primero, $segundo, $tercero], $this->demand(400, 300), $this->now());

        self::assertSame(300, $segundo->requiredWords(), 'Ya no es el último.');
        self::assertSame(400, $tercero->requiredWords());
    }

    /**
     * Una obra de un solo capítulo: ese capítulo es el último, así que
     * responde el cuestionario entero. Sin esto, una obra corta no cobraría
     * nunca las preguntas de cierre.
     */
    public function testTheOnlyChapterOfAWorkAnswersEverything(): void
    {
        $unico = $this->chapter(1, 1000);

        $this->pricing->reprice([$unico], $this->demand(400, 300), $this->now());

        self::assertSame(400, $unico->requiredWords());
    }

    /**
     * Los dos hechos que forman un precio llegan por separado. Un capítulo
     * que se adelanta a su cuestionario se cobra por lo que se lee, y el
     * suelo impide que salga gratis.
     */
    public function testAChapterWithNoQuestionnaireYetIsPricedOnItsLengthAlone(): void
    {
        $capitulo = $this->chapter(1, 4200);

        $this->pricing->reprice([$capitulo], null, $this->now());

        self::assertSame(0, $capitulo->requiredWords());
        self::assertSame(5, $capitulo->price());
    }

    public function testAWorkWithNoChaptersIsNotAProblem(): void
    {
        $this->pricing->reprice([], $this->demand(400, 300), $this->now());

        $this->expectNotToPerformAssertions();
    }

    /**
     * Repreciar dos veces con los mismos datos da el mismo resultado. Es lo
     * que permite reconstruir el read model reprocesando eventos.
     */
    public function testRepricingIsIdempotent(): void
    {
        $capitulo = $this->chapter(1, 3000);

        $this->pricing->reprice([$capitulo], $this->demand(300, 300), $this->now());
        $primero = $capitulo->price();

        $this->pricing->reprice([$capitulo], $this->demand(300, 300), $this->now());

        self::assertSame($primero, $capitulo->price());
    }

    private function chapter(int $position, int $wordCount): ChapterPrice
    {
        return new ChapterPrice(
            ChapterId::generate(),
            $this->work,
            UserId::generate(),
            $position,
            $wordCount,
            0,
            new ChapterPricing(),
            $this->now(),
        );
    }

    private function demand(int $requiredWords, int $forEveryChapter): WorkQuestionnaireDemand
    {
        return new WorkQuestionnaireDemand($this->work, 1, $requiredWords, $forEveryChapter, $this->now());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-23 12:00:00');
    }
}
