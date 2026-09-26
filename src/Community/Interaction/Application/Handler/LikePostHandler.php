<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\LikePost;
use LectoresBeta\Community\Interaction\Domain\Entity\PostLike;
use LectoresBeta\Community\Interaction\Domain\Repository\PostLikeRepository;
use LectoresBeta\Community\Post\Application\Service\VisiblePost;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Apoyar una publicación y retirarlo (`FEAT-COM-008`).
 *
 * **Las dos mitades viven juntas** porque tienen que seguir siendo simétricas:
 * separadas, el día que una toque el contador de otra manera nadie lo notará
 * hasta que un muro enseñe un número imposible.
 *
 * `VisiblePost` es lo que impide el agujero clásico de los contadores: sin
 * él, no haría falta leer una publicación para saber, por el error que
 * devuelve, que está ahí.
 *
 * Las dos son **idempotentes** (`RN-3`), y no por elegancia: el botón se
 * pulsa dos veces sin querer y el cliente reintenta cuando la red falla.
 */
final readonly class LikePostHandler
{
    public function __construct(
        private VisiblePost $visible,
        private PostRepository $posts,
        private PostLikeRepository $likes,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function like(LikePost $command): int
    {
        $post = $this->visible->to($command->postId, $command->memberId);
        $member = MemberId::fromString($command->memberId);

        if (null !== $this->likes->between($member, $post->id())) {
            return $post->likeCount();
        }

        $this->session->execute(function () use ($post, $member): void {
            $this->likes->save(new PostLike($post->id(), $member, $this->clock->now()));
            $post->liked();
            $this->posts->save($post);
        });

        return $post->likeCount();
    }

    public function unlike(LikePost $command): int
    {
        $post = $this->visible->to($command->postId, $command->memberId);
        $member = MemberId::fromString($command->memberId);
        $like = $this->likes->between($member, $post->id());

        if (null === $like) {
            return $post->likeCount();
        }

        $this->session->execute(function () use ($post, $like): void {
            $this->likes->remove($like);
            $post->unliked();
            $this->posts->save($post);
        });

        return $post->likeCount();
    }
}
