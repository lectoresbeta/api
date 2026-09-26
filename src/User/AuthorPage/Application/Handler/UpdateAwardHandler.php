<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\AuthorPage\Application\Command\UpdateAward;
use LectoresBeta\User\AuthorPage\Application\DTO\AwardView;
use LectoresBeta\User\AuthorPage\Application\Service\MyAward;
use LectoresBeta\User\AuthorPage\Domain\Repository\AwardRepository;

/**
 * Corregir un premio ya declarado (`FEAT-USR-030`).
 *
 * Se toca **solo lo que viene**. Mandar la ficha entera para cambiar un año
 * obliga al cliente a reenviar lo que no toca, y un campo que se olvida se
 * borra sin querer.
 */
final readonly class UpdateAwardHandler
{
    public function __construct(
        private MyAward $myAward,
        private AwardRepository $awards,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateAward $command): AwardView
    {
        $award = $this->myAward->of($command->userId, $command->awardId);

        if ($command->titleGiven) {
            $award->retitle($command->title ?? '');
        }

        if ($command->awardedByGiven) {
            $award->setAwardedBy($command->awardedBy);
        }

        if ($command->yearGiven) {
            $award->setYear($command->year, $this->clock->now());
        }

        if ($command->noteGiven) {
            $award->setNote($command->note);
        }

        if ($command->urlGiven) {
            $award->setUrl($command->url);
        }

        $this->session->execute(function () use ($award): void {
            $this->awards->save($award);
        });

        return AwardView::of($award);
    }
}
