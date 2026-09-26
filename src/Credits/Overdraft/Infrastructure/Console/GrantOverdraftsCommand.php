<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Infrastructure\Console;

use LectoresBeta\Credits\Overdraft\Application\Service\GrantOverdrafts;
use LectoresBeta\Credits\Overdraft\Domain\ValueObject\ReactivationCandidate;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * El reparto periódico del cupo de descubiertos (`FEAT-CRD-019`).
 *
 * **Con qué se programa es decisión de operación**, como la purga de alias:
 * atarlo al código significaría que cambiar el día es un despliegue. La
 * propuesta de la ficha es semanal, que es el periodo del cupo.
 *
 * `--dry-run` enseña a quién se elegiría sin conceder nada, que es lo que
 * alguien quiere la primera vez que lo lanza contra datos de verdad: este
 * comando genera deuda, y mirar antes de tocar no es una cortesía.
 */
#[AsCommand(
    name: 'lectoresbeta:credits:grant-overdrafts',
    description: 'Reparte el cupo de correcciones en descubierto del periodo.',
)]
final class GrantOverdraftsCommand extends Command
{
    public function __construct(private readonly GrantOverdrafts $grant)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Enseña a quién se elegiría y no concede nada.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');

        $granted = ($this->grant)($dryRun);

        if ([] === $granted) {
            // Cero es una respuesta, no un fallo: puede que el cupo esté
            // gastado, que esté a cero, o que no haya nadie que cumpla las
            // tres condiciones. Ninguna de las tres es un error.
            $io->success('Ningún autor elegible en este periodo.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Autor', 'Capítulo', 'Precio', 'Correcciones dadas', 'Última actividad'],
            array_map(
                static fn (ReactivationCandidate $candidate): array => [
                    $candidate->authorId->value(),
                    $candidate->chapterId->value(),
                    $candidate->price,
                    $candidate->correctionsGiven,
                    $candidate->lastActiveAt->format('Y-m-d'),
                ],
                $granted,
            ),
        );

        $io->success($dryRun
            ? \sprintf('%d autores se elegirían. No se ha concedido nada.', \count($granted))
            : \sprintf('%d descubiertos concedidos.', \count($granted)));

        return Command::SUCCESS;
    }
}
