<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Monitoring\Infrastructure\Console;

use LectoresBeta\Credits\Monitoring\Domain\Service\AccountingInvariant;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * La comprobación periódica de la invariante contable (`FEAT-CRD-012`
 * `RN-3`).
 *
 * **Su incumplimiento es una alerta, no una línea de log.** De ahí las dos
 * salidas: el registro a nivel `error`, que es lo que dispara un aviso en
 * cualquier sistema de monitorización, y el código de salida distinto de
 * cero, que es lo que hace fallar el trabajo programado y lo hace visible
 * aunque nadie esté mirando los registros.
 *
 * Un comando y no un chequeo de `/health` por dos razones: `/health` es
 * público, y esto suma sobre todo el histórico de movimientos —no es lo que
 * se pregunta cada diez segundos—.
 *
 * **Con qué frecuencia se ejecuta es decisión de operación** (`C-33`, que la
 * ficha propone diaria). Aquí no se programa nada: programarlo desde el
 * código ataría la frecuencia a un despliegue.
 */
#[AsCommand(
    name: 'credits:check-invariant',
    description: 'Comprueba que la suma de los saldos coincide con lo que la economía ha emitido',
)]
final class CheckAccountingInvariantCommand extends Command
{
    public function __construct(
        private readonly AccountingInvariant $invariant,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $check = $this->invariant->check();
        $io = new SymfonyStyle($input, $output);

        $io->definitionList(
            ['Emitido por los grifos' => $check->issued],
            ['Suma de movimientos' => $check->moved],
            ['Suma de saldos' => $check->balances],
        );

        if ($check->holds()) {
            $io->success('La invariante contable se cumple.');

            return Command::SUCCESS;
        }

        $this->logger->error('The credit accounting invariant does not hold.', [
            'failure' => $check->failure(),
            'issued' => $check->issued,
            'moved' => $check->moved,
            'balances' => $check->balances,
        ]);

        $io->error(\sprintf('La invariante contable NO se cumple: %s.', (string) $check->failure()));

        return Command::FAILURE;
    }
}
