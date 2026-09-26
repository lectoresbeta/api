<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Handler;

use LectoresBeta\Community\Interaction\Domain\Repository\PostLikeRepository;
use LectoresBeta\Community\Post\Application\Command\DeletePost;
use LectoresBeta\Community\Post\Domain\Exception\PostNotFound;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Retirar una publicación propia (`FEAT-COM-002` `RN-13`).
 *
 * **Definitivo y para todos**, incluido su autor. No hay papelera: «eliminar»
 * tiene que significar lo que la gente cree que significa, y una publicación
 * que sigue apareciéndole a quien la borró es la peor manera de descubrir que
 * no.
 *
 * Es un borrado lógico y eso no lo contradice: la fila se queda porque los
 * comentarios cuelgan de ella, pero deja de servirse en todas partes.
 *
 * Borrar lo ya borrado **no es un error**: el estado que se pedía ya se
 * cumple, y quien lo pide dos veces suele ser un doble clic.
 */
final readonly class DeletePostHandler
{
    public function __construct(
        private PostRepository $posts,
        private PostLikeRepository $likes,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeletePost $command): void
    {
        try {
            $post = $this->posts->ofId(PostId::fromString($command->postId));
        } catch (InvalidValue) {
            throw PostNotFound::create();
        }

        if (null === $post || $post->authorId()->value() !== $command->authorId) {
            throw PostNotFound::create();
        }

        $post->delete($this->clock->now());

        $this->session->execute(function () use ($post): void {
            $this->posts->save($post);

            // Los apoyos se van con ella (`FEAT-COM-008` `RN-9`): filas
            // apuntando a algo que ya no está solo sirven para que un día
            // alguien las cuente.
            $this->likes->removeAllOf($post->id());
        });
    }
}
