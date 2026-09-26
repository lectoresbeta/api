<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Work;

use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\BetaReaderAccessMode;
use LectoresBeta\Work\Manuscript\Domain\Service\WorkReadPolicy;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkTitle;
use PHPUnit\Framework\TestCase;

/**
 * Quién puede leer una obra (`FEAT-WRK-004`).
 *
 * Es la regla más peligrosa del backend: esta plataforma custodia obra
 * literaria inédita, y una fuga aquí es el peor fallo que tiene el producto.
 * Por eso casi todo este fichero comprueba quién **no** puede leer.
 */
final class WorkReadPolicyTest extends TestCase
{
    private const AUTHOR = '01a0cfd8-0000-7000-8000-00000000000a';
    private const STRANGER = '01a0cfd8-0000-7000-8000-00000000000b';

    public function testTheAuthorAlwaysReadsTheirOwnWork(): void
    {
        foreach ([$this->draft(), $this->published(), $this->inCorrection()] as $work) {
            self::assertTrue($this->allows($work, self::AUTHOR));
        }
    }

    /**
     * Un borrador **no existe** para nadie más. Es obra inédita que su autor
     * todavía no ha decidido enseñar.
     */
    public function testADraftDoesNotExistForAnybodyElse(): void
    {
        self::assertFalse($this->allows($this->draft(), self::STRANGER));
    }

    public function testAPublishedPublicWorkIsReadableByAnyone(): void
    {
        self::assertTrue($this->allows($this->published(), self::STRANGER));
        self::assertTrue($this->allows($this->inCorrection(), self::STRANGER));
    }

    /**
     * `ON_REQUEST` y `PRIVATE` dependen de un acceso concedido en `Reading`,
     * un contexto que todavía no existe. Hasta que exista, la respuesta es
     * «no»: es el lado seguro, y el que no habrá que cambiar.
     */
    public function testRestrictedAccessModesAreClosedUntilReadingExists(): void
    {
        foreach ([BetaReaderAccessMode::ON_REQUEST, BetaReaderAccessMode::PRIVATE] as $mode) {
            $work = $this->published($mode);

            self::assertFalse($this->allows($work, self::STRANGER));
            self::assertTrue($this->allows($work, self::AUTHOR), 'Su autor la lee igualmente.');
        }
    }

    public function testABlockedWorkDisappearsForEverybodyButItsAuthor(): void
    {
        $work = $this->published();
        $work->block(new \DateTimeImmutable());

        self::assertFalse($this->allows($work, self::STRANGER));
        self::assertTrue($this->allows($work, self::AUTHOR));
    }

    public function testAnAdultsOnlyWorkIsNotServedToAMinor(): void
    {
        $work = $this->published();
        $work->classify(true, new \DateTimeImmutable());

        self::assertFalse($this->allows($work, self::STRANGER, readerIsOfAge: false));
        self::assertTrue($this->allows($work, self::STRANGER, readerIsOfAge: true));
    }

    /**
     * El autor lee lo suyo sea cual sea su edad: es su texto, y ya lo ha
     * escrito.
     */
    public function testTheAgeGateDoesNotApplyToTheAuthor(): void
    {
        $work = $this->draft();
        $work->classify(true, new \DateTimeImmutable());

        self::assertTrue($this->allows($work, self::AUTHOR, readerIsOfAge: false));
    }

    /**
     * La quinta puerta, y la que hace que `ON_REQUEST` y `PRIVATE` signifiquen
     * algo: hasta que existió, conceder acceso a alguien le dejaba corregir
     * una obra que no podía leer.
     */
    public function testABetaReaderReadsWhateverTheModeSays(): void
    {
        $restringida = $this->published(BetaReaderAccessMode::PRIVATE);

        self::assertFalse($this->allows($restringida, self::STRANGER));
        self::assertTrue($this->allows($restringida, self::STRANGER, isBetaReader: true));
    }

    /**
     * Pero el acceso concedido no salta las puertas anteriores: un borrador
     * sigue sin existir para nadie más que su autor, y la edad sigue siendo
     * la edad.
     */
    public function testAccessDoesNotOpenTheGatesBeforeIt(): void
    {
        self::assertFalse(
            $this->allows($this->draft(BetaReaderAccessMode::PRIVATE), self::STRANGER, isBetaReader: true),
            'Un borrador no lo lee nadie más que su autor.',
        );

        $adultos = $this->published(BetaReaderAccessMode::PRIVATE);
        $adultos->classify(true, new \DateTimeImmutable());

        self::assertFalse(
            $this->allows($adultos, self::STRANGER, readerIsOfAge: false, isBetaReader: true),
            'Tener acceso no da la edad.',
        );
    }

    private function allows(Work $work, string $reader, bool $readerIsOfAge = true, bool $isBetaReader = false): bool
    {
        return (new WorkReadPolicy())->allows($work, AuthorId::fromString($reader), $readerIsOfAge, $isBetaReader);
    }

    private function draft(BetaReaderAccessMode $mode = BetaReaderAccessMode::PUBLIC): Work
    {
        return new Work(
            WorkId::generate(),
            AuthorId::fromString(self::AUTHOR),
            WorkTitle::fromString('La ciudad de los pájaros'),
            new \DateTimeImmutable(),
            $mode,
        );
    }

    private function published(BetaReaderAccessMode $mode = BetaReaderAccessMode::PUBLIC): Work
    {
        $work = $this->draft($mode);
        $work->recountContent(1200, 1, new \DateTimeImmutable());
        $work->publish(new \DateTimeImmutable());

        return $work;
    }

    private function inCorrection(): Work
    {
        $work = $this->published();
        $work->openForCorrection(new \DateTimeImmutable());

        return $work;
    }
}
