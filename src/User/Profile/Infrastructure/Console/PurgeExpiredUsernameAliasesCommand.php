<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Console;

use LectoresBeta\User\Profile\Application\Service\PurgeExpiredAliases;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * La purga diaria de alias caducados (`FEAT-USR-036`).
 *
 * **Que saltarse una ejecución sea inocuo es el diseño, no la suerte.** Un
 * alias caducado ya no resuelve y ya no ocupa su nombre: lo decide su fecha.
 * Esto solo retira filas que no sirven para nada, así que es de los pocos
 * procesos programados que no hay que vigilar de cerca.
 *
 * El borrado es **real** (`RN-4c`): desaparece la fila. Ni marca de borrado,
 * ni tabla de archivo, ni histórico. Un alias purgado deja de existir, y eso
 * es lo correcto — guardar para siempre qué nombre usó alguien antes sería
 * conservar un dato personal que ya no sirve a nadie.
 *
 * **Con qué se programa es decisión de operación** (`N-15`), y por eso aquí
 * no se programa nada: atarlo al código significaría que cambiar la hora es
 * un despliegue. La propuesta de la ficha es a diario, en franja de bajo
 * tráfico.
 */
#[AsCommand(
    name: 'lectoresbeta:user:purge-expired-username-aliases',
    description: 'Elimina los alias de nombre de usuario que ya han caducado.',
)]
final class PurgeExpiredUsernameAliasesCommand extends Command
{
    public function __construct(private readonly PurgeExpiredAliases $purge)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Cuenta cuántos borraría y no borra ninguno.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');

        $purged = ($this->purge)($dryRun);

        // El recuento se registra siempre (`RN-5`), también cuando es cero:
        // un cero dice que el comando corrió, que es justo lo que alguien
        // busca cuando sospecha que dejó de correr.
        $io->success($dryRun
            ? \sprintf('%d alias caducados se borrarían. No se ha borrado ninguno.', $purged)
            : \sprintf('%d alias caducados borrados.', $purged));

        return Command::SUCCESS;
    }
}
