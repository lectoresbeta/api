<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Shared\Health;

use LectoresBeta\Shared\Application\Health\CheckHealth;
use LectoresBeta\Shared\Domain\Health\CheckResult;
use LectoresBeta\Shared\Domain\Health\HealthCheck;
use LectoresBeta\Shared\Domain\Health\HealthReport;
use LectoresBeta\Shared\Domain\Health\HealthStatus;
use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class CheckHealthTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = FrozenClock::at('2026-09-24T10:00:00');
    }

    public function testWithNothingToCheckTheSystemIsHealthy(): void
    {
        $report = $this->reportOf();

        self::assertTrue($report->isHealthy());
        self::assertSame([], $report->results);
        self::assertEquals($this->clock->now(), $report->checkedAt);
    }

    public function testEverythingUpMeansHealthy(): void
    {
        $report = $this->reportOf(
            $this->check('database', CheckResult::up('database', 1.0)),
            $this->check('message_broker', CheckResult::up('message_broker', 2.0)),
        );

        self::assertTrue($report->isHealthy());
        self::assertSame(HealthStatus::UP, $report->status);
    }

    /**
     * Pesimista a propósito: una dependencia caída basta. Un endpoint de salud
     * que responde «casi bien» obliga a quien lo lee a decidir qué significa
     * eso, y a las tres de la mañana nadie quiere decidir nada.
     */
    public function testOneCheckDownBringsTheWholeReportDown(): void
    {
        $report = $this->reportOf(
            $this->check('database', CheckResult::up('database', 1.0)),
            $this->check('message_broker', CheckResult::down('message_broker', 30.0)),
        );

        self::assertFalse($report->isHealthy());
        self::assertSame(HealthStatus::DOWN, $report->status);
    }

    /**
     * Un chequeo que no aplica no es un fallo. Es la diferencia entre
     * «funciona» y «no se ha preguntado».
     */
    public function testASkippedCheckIsNotAFailure(): void
    {
        $report = $this->reportOf(
            $this->check('message_broker', CheckResult::skipped('message_broker', 'in-memory')),
        );

        self::assertTrue($report->isHealthy());
        self::assertSame(HealthStatus::SKIPPED, $report->results[0]->status);
    }

    /**
     * Todos los chequeos se ejecutan aunque uno ya haya fallado: «la base de
     * datos está caída» y «la base de datos y la cola están caídas» son
     * incidentes distintos.
     */
    public function testEveryCheckRunsEvenAfterOneHasFailed(): void
    {
        /** @var \ArrayObject<int, string> $ejecutados */
        $ejecutados = new \ArrayObject();

        $report = $this->reportOf(
            $this->check('database', CheckResult::down('database', 5.0), $ejecutados),
            $this->check('message_broker', CheckResult::down('message_broker', 5.0), $ejecutados),
            $this->check('storage', CheckResult::up('storage', 1.0), $ejecutados),
        );

        self::assertSame(['database', 'message_broker', 'storage'], $ejecutados->getArrayCopy());
        self::assertCount(3, $report->results);
    }

    /**
     * El orden del informe es el orden en que se registran los chequeos, no
     * el de sus resultados. Quien lo lee espera ver siempre lo mismo arriba.
     */
    public function testTheReportKeepsTheOrderOfTheChecks(): void
    {
        $report = $this->reportOf(
            $this->check('message_broker', CheckResult::up('message_broker', 1.0)),
            $this->check('database', CheckResult::up('database', 1.0)),
        );

        self::assertSame(
            ['message_broker', 'database'],
            array_map(static fn (CheckResult $r): string => $r->name, $report->results),
        );
    }

    private function reportOf(HealthCheck ...$checks): HealthReport
    {
        return (new CheckHealth($checks, $this->clock))();
    }

    /**
     * Un doble que además apunta que le han preguntado, para poder comprobar
     * que se ejecutan todos y en orden.
     *
     * @param \ArrayObject<int, string>|null $log
     */
    private function check(string $name, CheckResult $result, ?\ArrayObject $log = null): HealthCheck
    {
        return new class($name, $result, $log ?? new \ArrayObject()) implements HealthCheck {
            /**
             * @param \ArrayObject<int, string> $log
             */
            public function __construct(
                private readonly string $name,
                private readonly CheckResult $result,
                private readonly \ArrayObject $log,
            ) {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function run(): CheckResult
            {
                $this->log->append($this->name);

                return $this->result;
            }
        };
    }
}
