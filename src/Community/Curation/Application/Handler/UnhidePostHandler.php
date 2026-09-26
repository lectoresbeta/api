<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Handler;

use LectoresBeta\Community\Curation\Application\Command\UnhidePost;
use LectoresBeta\Community\Curation\Domain\Repository\HiddenPostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Volver a mostrar lo que se había escondido (`FEAT-COM-022` `RN-3`).
 *
 * Tampoco pregunta por la visibilidad, por lo mismo que quitar de guardados:
 * lo que se esconde tiene que poder volver aunque la publicación haya dejado
 * de alcanzarte.
 */
final readonly class UnhidePostHandler
{
    public function __construct(
        private HiddenPostRepository $hidden,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(UnhidePost $command): void
    {
        try {
            $member = MemberId::fromString($command->memberId);
            $postId = PostId::fromString($command->postId);
        } catch (InvalidValue) {
            return;
        }

        $this->session->execute(function () use ($member, $postId): void {
            $this->hidden->remove($member, $postId);
        });
    }
}
