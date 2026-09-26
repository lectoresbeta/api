<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Handler;

use LectoresBeta\Reading\BetaReaderGroup\Application\Command\RenameBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Application\Service\OwnGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Exception\GroupRefused;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupRepository;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupName;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

final readonly class RenameBetaReaderGroupHandler
{
    public function __construct(
        private OwnGroup $ownGroup,
        private BetaReaderGroupRepository $groups,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(RenameBetaReaderGroup $command): BetaReaderGroup
    {
        $group = $this->ownGroup->of($command->groupId, $command->authorId);
        $name = BetaReaderGroupName::fromString($command->name ?? '');

        // `$except` es este mismo grupo: renombrarlo con el nombre que ya
        // tiene —cambiando solo una mayúscula, por ejemplo— no choca consigo
        // mismo.
        if ($this->groups->nameIsTaken($group->authorId(), $name, $group->id())) {
            throw GroupRefused::nameAlreadyUsed();
        }

        $group->rename($name);

        $this->session->execute(function () use ($group): void {
            $this->groups->save($group);
        });

        return $group;
    }
}
