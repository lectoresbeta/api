<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Console;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Designar la cuenta con la que habla la plataforma (`FEAT-COM-038`).
 *
 * **Por consola y no desde la API**, por lo mismo que el primer administrador
 * (`FEAT-MOD-012` `RN-6`): quien pueda marcar una cuenta como institucional
 * puede hacer que sus mensajes lleguen a todo el mundo con la voz de la
 * plataforma. No es un privilegio que deba poder concederse por una petición
 * HTTP, por muy protegida que esté.
 *
 * **No crea la cuenta.** Quien va a hablar en nombre de la plataforma se
 * registra y activa como todo el mundo; esto solo la marca. Una cuenta que
 * existe sin que nadie la haya dado de alta es una cuenta que nadie vigila.
 *
 * El relevo se hace **marcando la nueva y desmarcando la vieja**, en dos
 * ejecuciones y en ese orden, para que no haya un instante sin ninguna.
 */
#[AsCommand(
    name: 'lectoresbeta:platform:account',
    description: 'Designates an existing, activated account as the one the platform speaks with.',
)]
final class DesignatePlatformAccountCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TransactionalSession $session,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The account to designate.')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Stop this account being the institutional one.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $address */
        $address = $input->getArgument('email');
        $user = $this->users->ofEmail(Email::fromString($address));

        if (null === $user) {
            $io->error('There is no account with that address.');

            return Command::FAILURE;
        }

        $institutional = true !== $input->getOption('revoke');
        $user->beInstitutional($institutional, $this->clock->now());

        $this->session->execute(function () use ($user): void {
            $this->users->save($user);
        });

        $io->success($institutional
            ? \sprintf('%s is now the account the platform speaks with.', $address)
            : \sprintf('%s no longer speaks for the platform.', $address));

        return Command::SUCCESS;
    }
}
