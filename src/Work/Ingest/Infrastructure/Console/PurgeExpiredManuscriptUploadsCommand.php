<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Infrastructure\Console;

use LectoresBeta\Work\Ingest\Application\Service\PurgeExpiredUploads;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * La purga de manuscritos subidos y nunca confirmados (`FEAT-WRK-002`).
 *
 * Cada fila que retira es una novela entera, así que conviene que corra; y
 * saltarse una ejecución no rompe nada, porque una subida caducada ya no se
 * puede confirmar. Como en el resto del proyecto, **aquí no se programa
 * nada**: atar el horario al código haría que cambiarlo fuera un despliegue.
 */
#[AsCommand(
    name: 'lectoresbeta:work:purge-expired-manuscript-uploads',
    description: 'Elimina los manuscritos subidos que nadie llegó a confirmar.',
)]
final class PurgeExpiredManuscriptUploadsCommand extends Command
{
    public function __construct(private readonly PurgeExpiredUploads $purge)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $removed = ($this->purge)();

        $style->success(\sprintf('Subidas retiradas: %d.', $removed));

        return Command::SUCCESS;
    }
}
