<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderGroup\Application\Command\CreateBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Exception\GroupRefused;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupRepository;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupName;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Crear una lista vacía con un nombre (`FEAT-RDG-007`).
 */
final readonly class CreateBetaReaderGroupHandler
{
    /** `RN-4`. Más de cincuenta listas no se eligen, se buscan. */
    public const MAX_GROUPS = 50;

    public function __construct(
        private BetaReaderGroupRepository $groups,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateBetaReaderGroup $command): BetaReaderGroup
    {
        $authorId = AuthorId::fromString($command->authorId);
        $name = BetaReaderGroupName::fromString($command->name ?? '');

        if ($this->groups->countOfAuthor($authorId) >= self::MAX_GROUPS) {
            throw GroupRefused::tooManyGroups();
        }

        if ($this->groups->nameIsTaken($authorId, $name, null)) {
            throw GroupRefused::nameAlreadyUsed();
        }

        $group = new BetaReaderGroup(
            BetaReaderGroupId::generate(),
            $authorId,
            $name,
            $this->clock->now(),
        );

        $this->session->execute(function () use ($group): void {
            $this->groups->save($group);
        });

        return $group;
    }
}
