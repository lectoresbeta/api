<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Repository;

use LectoresBeta\Community\Interaction\Domain\Entity\PostComment;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface PostCommentRepository
{
    public function save(PostComment $comment): void;

    /**
     * Un comentario vivo. Uno eliminado responde `null`: ha dejado de existir
     * para todo el mundo, su autor incluido.
     */
    public function ofId(PostCommentId $commentId): ?PostComment;

    /**
     * Los comentarios **de primer nivel** de una publicación.
     *
     * Las respuestas no salen aquí: cuelgan de su comentario y se piden
     * aparte (`FEAT-COM-031`). Mezclarlas obligaría al cliente a reconstruir
     * el árbol a partir de una lista plana.
     *
     * @param 'RECENT'|'OLDEST' $sort
     *
     * @return list<PostComment> con una fila de más para saber si hay página
     *                           siguiente
     */
    public function topLevelOf(PostId $postId, string $sort, ?Cursor $after, int $limit): array;

    /**
     * Las respuestas de un comentario, **siempre de la más antigua a la más
     * reciente**: un hilo se lee en el orden en que se dijo.
     *
     * @return list<PostComment>
     */
    public function repliesOf(PostCommentId $rootId, ?Cursor $after, int $limit): array;

    /**
     * Todas las respuestas vivas de un comentario, sin paginar.
     *
     * Existe para **una** cosa: retirarlas con su raíz (`FEAT-COM-031`
     * `RN-7`). No sirve para pintar un hilo —para eso está `repliesOf`, que
     * pagina— porque el número de respuestas de un comentario popular no
     * tiene techo.
     *
     * @return list<PostComment>
     */
    public function allRepliesOf(PostCommentId $rootId): array;
}
