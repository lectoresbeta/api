<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\UndoRepost;
use LectoresBeta\Community\Interaction\Domain\Repository\PostRepostRepository;
use LectoresBeta\Community\Post\Domain\Exception\PostNotFound;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Retirar un repost propio (`FEAT-COM-019` `RN-9`).
 *
 * **No comprueba la audiencia del original**, a diferencia de repostear, y es
 * a propósito: deshacer algo propio tiene que funcionar aunque el original
 * haya dejado de ser visible —el autor lo restringió después— porque si no,
 * quedaría un repost que su dueño no puede quitar.
 *
 * Deshacer lo ya deshecho **no es un error**: el estado que se pedía ya se
 * cumple, y quien lo pide dos veces suele ser un doble clic.
 */
final readonly class UndoRepostHandler
{
    public function __construct(
        private PostRepostRepository $reposts,
        private PostRepository $posts,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(UndoRepost $command): void
    {
        try {
            $postId = PostId::fromString($command->postId);
            $member = MemberId::fromString($command->memberId);
        } catch (InvalidValue) {
            throw PostNotFound::create();
        }

        $repost = $this->reposts->between($member, $postId);

        if (null === $repost) {
            return;
        }

        $post = $this->posts->ofId($postId);

        $this->session->execute(function () use ($repost, $post): void {
            $this->reposts->remove($repost);

            if (null !== $post) {
                $post->repostUndone();
                $this->posts->save($post);
            }
        });
    }
}
