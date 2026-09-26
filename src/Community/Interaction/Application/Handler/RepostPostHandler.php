<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\RepostPost;
use LectoresBeta\Community\Interaction\Domain\Entity\PostRepost;
use LectoresBeta\Community\Interaction\Domain\Event\PostReposted;
use LectoresBeta\Community\Interaction\Domain\Repository\PostRepostRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostRepostId;
use LectoresBeta\Community\Post\Application\Service\VisiblePost;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostBody;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Volver a sacar la publicación de otro (`FEAT-COM-019`).
 *
 * **No se repostea lo que no se puede ver** (`RN-4`), y por eso se pregunta
 * por `VisiblePost`: es la misma regla del muro, preguntada por un tercer
 * lado. Un original invisible y uno inexistente responden igual, que es
 * deliberado — distinguirlos revelaría que existe una publicación que esa
 * persona no debería conocer.
 *
 * **El botón alterna** (`C-12`): volver a pulsarlo deshace el repost, como
 * un «me gusta». Es lo que hace la gente cuando se arrepiente, y un segundo
 * `POST` que respondiera «ya estaba» dejaría la interfaz sin forma de
 * decirlo.
 *
 * Se puede repostear lo propio (`C-13`): sirve para volver a sacar algo
 * antiguo, y es lo que hacen las demás plataformas.
 */
final readonly class RepostPostHandler
{
    public function __construct(
        private VisiblePost $visible,
        private PostRepostRepository $reposts,
        private PostRepository $posts,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    /**
     * @return bool si ha quedado reposteada; `false` cuando el mismo gesto la
     *              ha deshecho
     */
    public function __invoke(RepostPost $command): bool
    {
        $post = $this->visible->to($command->postId, $command->memberId);
        $member = MemberId::fromString($command->memberId);
        $existing = $this->reposts->between($member, $post->id());

        if (null !== $existing) {
            $this->session->execute(function () use ($existing, $post): void {
                $this->reposts->remove($existing);
                $post->repostUndone();
                $this->posts->save($post);
            });

            return false;
        }

        $now = $this->clock->now();

        $repost = new PostRepost(
            PostRepostId::generate(),
            $post->id(),
            $member,
            $now,
            PostBody::fromString($command->comment)?->value(),
        );

        $this->session->execute(function () use ($repost, $post): void {
            $this->reposts->save($repost);
            $post->reposted();
            $this->posts->save($post);
        });

        $this->events->publish(new PostReposted(
            EventId::generate(),
            $post->id(),
            $post->authorId(),
            $member,
            $now,
        ));

        return true;
    }
}
