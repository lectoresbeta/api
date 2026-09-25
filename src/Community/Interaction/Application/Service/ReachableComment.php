<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Service;

use LectoresBeta\Community\Interaction\Domain\Entity\PostComment;
use LectoresBeta\Community\Interaction\Domain\Exception\CommentNotFound;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Application\Service\VisiblePost;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El hilo al que pertenece un comentario (`FEAT-COM-031` `RN-2`).
 *
 * **Los hilos son planos**: `parentCommentId` apunta siempre a un comentario
 * de primer nivel, nunca a otra respuesta. La invariante se resuelve aquí, en
 * un sitio, y el cliente no necesita saber cuál es el raíz — responde a lo
 * que tiene delante y el servidor lo cuelga donde toca.
 *
 * Está escrita una vez porque la usan las tres operaciones del hilo: crear
 * una respuesta, listarlas y comprobar que quien pregunta puede llegar. Si
 * estuviera en cada una, sería la tercera la que se olvidaría.
 */
final readonly class ReachableComment
{
    public function __construct(
        private VisiblePost $visible,
        private PostCommentRepository $comments,
    ) {
    }

    /**
     * El raíz del hilo, comprobando antes que su publicación **exista para
     * quien pregunta**.
     *
     * Un comentario no tiene audiencia propia: hereda entera la de su
     * publicación. Preguntarlo así —y no «dame este comentario»— es lo que
     * impide que responder sea la puerta trasera por la que alguien toca una
     * conversación que no debería leer.
     */
    public function rootOf(string $commentId, string $readerId): PostComment
    {
        $comment = $this->live($commentId);

        $this->visible->to($comment->postId()->value(), $readerId);

        return $this->root($comment);
    }

    /**
     * El mismo raíz, cuando la publicación ya se ha comprobado y se sabe cuál
     * es. Comprobarla dos veces solo la cargaría dos veces.
     */
    public function rootWithin(string $commentId, PostId $postId): PostComment
    {
        $comment = $this->live($commentId);

        if ($comment->postId()->value() !== $postId->value()) {
            throw CommentNotFound::create();
        }

        return $this->root($comment);
    }

    /**
     * **El comentario en sí**, no el raíz de su hilo, y solo si quien
     * pregunta llega a su publicación.
     *
     * Lo necesita apoyar un comentario (`FEAT-COM-030`): ahí el objetivo es
     * exactamente el que se señala, respuesta incluida, y subir el contador
     * del raíz sería contar en el sitio equivocado.
     *
     * Un comentario borrado no se alcanza: su texto ya no está, y apoyar algo
     * que no se ve sería la forma de averiguar que estuvo ahí.
     */
    public function itself(string $commentId, string $readerId): PostComment
    {
        $comment = $this->live($commentId);

        $this->visible->to($comment->postId()->value(), $readerId);

        if ($comment->isDeleted()) {
            throw CommentNotFound::create();
        }

        return $comment;
    }

    private function live(string $commentId): PostComment
    {
        try {
            $comment = $this->comments->ofId(PostCommentId::fromString($commentId));
        } catch (InvalidValue) {
            throw CommentNotFound::create();
        }

        return $comment ?? throw CommentNotFound::create();
    }

    private function root(PostComment $comment): PostComment
    {
        if (!$comment->isReply()) {
            return $comment;
        }

        $root = $this->comments->ofId($comment->parentCommentId() ?? throw CommentNotFound::create());

        return $root ?? throw CommentNotFound::create();
    }
}
