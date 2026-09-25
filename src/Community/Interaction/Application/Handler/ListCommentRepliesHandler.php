<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\DTO\CommentPage;
use LectoresBeta\Community\Interaction\Application\Query\ListCommentReplies;
use LectoresBeta\Community\Interaction\Application\Service\PageOfComments;
use LectoresBeta\Community\Interaction\Application\Service\ReachableComment;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentRepository;

/**
 * Las respuestas de un comentario (`FEAT-COM-031`).
 *
 * **Siempre de la más antigua a la más reciente**, sin desplegable que lo
 * cambie: un hilo se lee en el orden en que se dijo, y del revés obligaría a
 * leer hacia arriba para entender a qué contesta cada cosa.
 *
 * Si lo que se pide es una respuesta y no un comentario raíz, se resuelve al
 * raíz: los hilos son planos, así que «las respuestas de esta respuesta» son
 * las del hilo entero.
 */
final readonly class ListCommentRepliesHandler
{
    public function __construct(
        private ReachableComment $reachable,
        private PostCommentRepository $comments,
        private PageOfComments $page,
    ) {
    }

    public function __invoke(ListCommentReplies $query): CommentPage
    {
        $root = $this->reachable->rootOf($query->commentId, $query->readerId);
        $limit = $this->page->size($query->limit);

        return $this->page->of(
            $this->comments->repliesOf($root->id(), $this->page->after($query->cursor), $limit),
            $limit,
            $query->readerId,
        );
    }
}
