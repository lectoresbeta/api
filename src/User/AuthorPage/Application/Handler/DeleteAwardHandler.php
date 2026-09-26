<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\AuthorPage\Application\Command\DeleteAward;
use LectoresBeta\User\AuthorPage\Application\Service\MyAward;
use LectoresBeta\User\AuthorPage\Domain\Repository\AwardRepository;

final readonly class DeleteAwardHandler
{
    public function __construct(
        private MyAward $myAward,
        private AwardRepository $awards,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(DeleteAward $command): void
    {
        $award = $this->myAward->of($command->userId, $command->awardId);

        $this->session->execute(function () use ($award): void {
            $this->awards->remove($award);
        });
    }
}
