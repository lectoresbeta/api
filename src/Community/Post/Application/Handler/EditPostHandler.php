<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Handler;

use LectoresBeta\Community\Post\Application\Command\EditPost;
use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Enum\PostFormat;
use LectoresBeta\Community\Post\Domain\Exception\PostNotFound;
use LectoresBeta\Community\Post\Domain\Exception\PostRefused;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\PostBody;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Cambiar el texto de una publicación propia (`FEAT-COM-002` `RN-13`).
 *
 * **Solo el texto.** Ni la audiencia —ampliarla haría aparecer ante todos
 * algo escrito para un círculo cerrado— ni el adjunto, que es aquello a lo
 * que la gente respondió.
 *
 * La de otra persona **no existe**: `404`, no `403`. Un permiso denegado
 * sobre una publicación confirmaría que está ahí, y eso ya es información
 * sobre quien la escribió.
 *
 * Dejarla sin texto solo se acepta si le queda el adjunto. Vaciar del todo
 * una publicación es eliminarla, y para eso hay una operación que dice lo que
 * hace.
 */
final readonly class EditPostHandler
{
    public function __construct(
        private PostRepository $posts,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(EditPost $command): void
    {
        $post = $this->mine($command->postId, $command->authorId);
        $body = PostBody::fromString($command->body);

        if (null === $body && !self::hasAttachment($post)) {
            throw PostRefused::empty();
        }

        if (!$post->edit($body?->value() ?? '', $this->clock->now())) {
            return;
        }

        $this->session->execute(function () use ($post): void {
            $this->posts->save($post);
        });
    }

    private function mine(string $postId, string $authorId): Post
    {
        try {
            $post = $this->posts->ofId(PostId::fromString($postId));
        } catch (InvalidValue) {
            throw PostNotFound::create();
        }

        if (null === $post || $post->authorId()->value() !== $authorId) {
            throw PostNotFound::create();
        }

        return $post;
    }

    /**
     * El formato es la respuesta: se derivó del adjunto al publicar, así que
     * cualquier cosa que no sea `TEXT` significa que hay uno.
     */
    private static function hasAttachment(Post $post): bool
    {
        return PostFormat::TEXT !== $post->format();
    }
}
