<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\EventProcessing\Infrastructure\Console;

use LectoresBeta\Credits\EventProcessing\Application\Service\PurgeProcessedEvents;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * La purga del registro de deduplicación (`FEAT-CRD-011` `RN-7`).
 *
 * **Que saltarse una ejecución sea inocuo es el diseño, no la suerte.** Una
 * fila caducada no hace daño mientras esté; lo único que produce dejarla es
 * una tabla más grande. Ninguna regla de negocio depende de que esto haya
 * corrido, lo que lo convierte en uno de los pocos procesos programados que
 * no hay que vigilar de cerca.
 *
 * **Con qué se programa es decisión de operación**, y por eso aquí no se
 * programa nada: atarlo al código significaría que cambiar la hora es un
 * despliegue. La propuesta es a diario, en franja de bajo tráfico, igual que
 * la purga de alias (`FEAT-USR-036`).
 */
#[AsCommand(
    name: 'lectoresbeta:credits:purge-processed-events',
    description: 'Elimina del registro de deduplicación las filas que ya han caducado.',
)]
final class PurgeProcessedEventsCommand extends Command
{
    public function __construct(private readonly PurgeProcessedEvents $purge)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // El recuento se registra siempre, también cuando es cero: un cero
        // dice que el comando corrió, que es justo lo que alguien busca
        // cuando sospecha que dejó de correr.
        $io->success(\sprintf(
            '%d filas de deduplicación borradas (más de %d días).',
            ($this->purge)(),
            PurgeProcessedEvents::RETENTION_DAYS,
        ));

        return Command::SUCCESS;
    }
}
