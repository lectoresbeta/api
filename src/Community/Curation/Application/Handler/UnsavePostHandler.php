<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Handler;

use LectoresBeta\Community\Curation\Application\Command\UnsavePost;
use LectoresBeta\Community\Curation\Domain\Repository\SavedPostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Quitar de guardados (`FEAT-COM-021` `RN-4`).
 *
 * **No pregunta si la publicación se puede ver**, al revés que guardar. Una
 * publicación que dejó de alcanzarte tiene que poder salir de tu lista: exigir
 * verla para quitarla dejaría filas que nadie puede borrar.
 */
final readonly class UnsavePostHandler
{
    public function __construct(
        private SavedPostRepository $saved,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(UnsavePost $command): void
    {
        try {
            $member = MemberId::fromString($command->memberId);
            $postId = PostId::fromString($command->postId);
        } catch (InvalidValue) {
            return;
        }

        $this->session->execute(function () use ($member, $postId): void {
            $this->saved->remove($member, $postId);
        });
    }
}
