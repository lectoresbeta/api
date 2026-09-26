<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Application\Command\AddAward;
use LectoresBeta\User\AuthorPage\Application\DTO\AwardView;
use LectoresBeta\User\AuthorPage\Domain\Entity\Award;
use LectoresBeta\User\AuthorPage\Domain\Exception\AwardRefused;
use LectoresBeta\User\AuthorPage\Domain\Repository\AwardRepository;
use LectoresBeta\User\AuthorPage\Domain\Service\AwardPolicy;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AwardId;

/**
 * Declarar un mérito (`FEAT-USR-030`).
 *
 * **Solo el título es obligatorio** (`RN-4`). Quien recuerda una mención de
 * hace veinte años no tiene por qué recordar el año ni tener un enlace, y
 * exigírselos le dejaría fuera un mérito que existió.
 *
 * No publica ningún evento (`RN-9`), y no es un olvido: esto no mueve
 * créditos, no cuenta como relato y no interesa a ningún otro contexto.
 */
final readonly class AddAwardHandler
{
    public function __construct(
        private AwardRepository $awards,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(AddAward $command): AwardView
    {
        $userId = UserId::fromString($command->userId);
        $now = $this->clock->now();

        if ($this->awards->countOfAuthor($userId) >= AwardPolicy::MAX_PER_AUTHOR) {
            throw AwardRefused::tooMany();
        }

        $award = Award::declare(AwardId::generate(), $userId, $command->title, $now);

        $award->setAwardedBy($command->awardedBy);
        $award->setYear($command->year, $now);
        $award->setNote($command->note);
        $award->setUrl($command->url);

        $this->session->execute(function () use ($award): void {
            $this->awards->save($award);
        });

        return AwardView::of($award);
    }
}
