<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionWindow;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use PHPUnit\Framework\TestCase;

/**
 * La puerta de una obra, proyectada en `Credits` (`FEAT-WRK-016`).
 *
 * **La versión decide, no la llegada**, y estas pruebas son la razón: la cola
 * reentrega, y sin versión las reentregas de «abierta» y «cerrada» se turnan
 * para siempre. Lo destapó el banco de pruebas funcionales, con 158 tests
 * cayendo por un ciclo en la cascada.
 *
 * La fecha no valía de desempate: publicar y abrir a corrección son dos clics
 * seguidos, así que las dos transiciones caen en el mismo segundo.
 */
final class CorrectionWindowTest extends TestCase
{
    public function testAWorkIsBornClosed(): void
    {
        self::assertFalse($this->born()->isOpen());
    }

    public function testOpeningAndClosingMoveTheDoor(): void
    {
        $window = $this->born();

        self::assertTrue($window->applyVersion(true, 1, $this->now()));
        self::assertTrue($window->isOpen());

        self::assertTrue($window->applyVersion(false, 2, $this->now()));
        self::assertFalse($window->isOpen());
    }

    /**
     * El caso que motiva el diseño: reentregar los dos hechos, en cualquier
     * orden y cuantas veces sea, deja la puerta donde la dejó el más nuevo.
     */
    public function testReplayingBothFactsChangesNothing(): void
    {
        $window = $this->born();

        $window->applyVersion(true, 1, $this->now());
        $window->applyVersion(false, 2, $this->now());

        foreach (range(1, 5) as $round) {
            self::assertFalse($window->applyVersion(true, 1, $this->now()), "Ronda $round");
            self::assertFalse($window->applyVersion(false, 2, $this->now()), "Ronda $round");
            self::assertFalse($window->isOpen(), "Ronda $round");
        }
    }

    /**
     * Y una apertura **posterior** sí vuelve a abrir: es otra decisión, no la
     * misma reentregada.
     */
    public function testAReopeningIsANewDecision(): void
    {
        $window = $this->born();

        $window->applyVersion(true, 1, $this->now());
        $window->applyVersion(false, 2, $this->now());

        self::assertTrue($window->applyVersion(true, 3, $this->now()));
        self::assertTrue($window->isOpen());
    }

    /**
     * Aplicar la misma versión con el mismo valor tampoco anuncia nada: no es
     * que no cambie el booleano, es que la versión ya estaba aplicada.
     */
    public function testTheSameVersionNeverApplies(): void
    {
        $window = $this->born();

        self::assertTrue($window->applyVersion(true, 1, $this->now()));
        self::assertFalse($window->applyVersion(false, 1, $this->now()), 'La versión 1 ya está aplicada.');
        self::assertTrue($window->isOpen());
    }

    /**
     * Una obra anterior a que este contexto escuchara nada nace por el lado
     * contrario al hecho que llega: si no, el primer hecho no cambiaría nada
     * y nadie recalcularía su corregibilidad.
     */
    public function testAWorkNobodyKnewAboutStillReactsToItsFirstFact(): void
    {
        $window = CorrectionWindow::unknownBefore(WorkId::generate(), open: true, version: 7, now: $this->now());

        self::assertFalse($window->isOpen());
        self::assertTrue($window->applyVersion(true, 7, $this->now()));
        self::assertTrue($window->isOpen());
    }

    private function born(): CorrectionWindow
    {
        return CorrectionWindow::closedAtBirth(WorkId::generate(), $this->now());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-26 10:00:00');
    }
}
