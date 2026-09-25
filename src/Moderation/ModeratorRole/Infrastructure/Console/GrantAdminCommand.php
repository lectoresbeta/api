<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Infrastructure\Console;

use LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorRole;
use LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler;
use LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * El primer administrador (`FEAT-MOD-012`).
 *
 * **Por consola y nunca desde la API** (`RN-6`), y eso es lo que hace que el
 * registro de auditoría signifique algo: si existiera un endpoint para
 * autoconcederse el rol, cualquier fallo de autorización sería catastrófico.
 *
 * **Y por comando y no por semilla.** Una semilla crea el administrador en
 * todos los entornos por igual, con credenciales conocidas: es el origen
 * clásico del `admin/admin` que sobrevive hasta producción. Un comando exige
 * una acción deliberada, sobre una cuenta real, en el entorno concreto.
 *
 * **No crea usuarios** (`RN-1`). Quien va a administrar se registra como todo
 * el mundo y activa su cuenta; esto solo le sube el nivel. Una cuenta con el
 * máximo privilegio y sin persona detrás es la que nadie vigila.
 *
 * El actor que queda en la auditoría es la propia cuenta, porque la consola
 * no tiene identidad propia — la tiene quien la ejecuta, y eso no se puede
 * saber desde aquí. Lo que sí queda es que **fue por consola**: es el único
 * camino por el que este cambio puede llegar sin un administrador detrás.
 */
#[AsCommand(
    name: 'lectoresbeta:admin:grant',
    description: 'Grants or revokes the administrator role on an existing, activated account.',
)]
final class GrantAdminCommand extends Command
{
    public function __construct(
        private readonly SetModeratorRoleHandler $setRole,
        private readonly RegisteredUsers $accounts,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The email of an existing, activated account.')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Takes the role away instead of granting it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $revoking = true === $input->getOption('revoke');

        $userId = $this->accounts->idOfEmail($email);

        if (null === $userId) {
            $console->error(\sprintf('There is no account with the address %s.', $email));

            return Command::FAILURE;
        }

        try {
            ($this->setRole)(new SetModeratorRole(
                actorId: SetModeratorRoleHandler::CONSOLE,
                userId: $userId,
                level: $revoking ? null : ModeratorLevel::ADMIN->value,
                fromConsole: true,
            ));
        } catch (\DomainException $refusal) {
            $console->error($refusal->getMessage());

            return Command::FAILURE;
        }

        $console->success($revoking
            ? \sprintf('%s is no longer an administrator.', $email)
            : \sprintf('%s is now an administrator.', $email));

        return Command::SUCCESS;
    }
}
