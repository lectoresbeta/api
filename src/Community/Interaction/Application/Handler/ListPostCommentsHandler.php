<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\DTO\CommentPage;
use LectoresBeta\Community\Interaction\Application\Query\ListPostComments;
use LectoresBeta\Community\Interaction\Application\Service\PageOfComments;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentRepository;
use LectoresBeta\Community\Post\Application\Service\VisiblePost;

/**
 * Los comentarios de una publicación (`FEAT-COM-006`).
 *
 * Primero se comprueba que la publicación existe **para quien pregunta**: si
 * su audiencia le excluye, sus comentarios tampoco son suyos que leer. Es la
 * misma regla que impide comentarla, preguntada por el otro lado.
 */
final readonly class ListPostCommentsHandler
{
    /**
     * Los órdenes que se pueden servir hoy.
     *
     * **«Más relevantes» es el que la pantalla enseña por defecto**, y ya se
     * puede servir: la fórmula es `apoyos + 2 × respuestas`, y los apoyos
     * llegaron con `FEAT-COM-030`. Una respuesta pesa el doble que un apoyo
     * porque cuesta más — sin ese peso sería «más gustados» con otro nombre.
     *
     * Un criterio no admitido responde `422` con los que sí, igual que en
     * «Mis relatos».
     */
    public const SORTS = ['RECENT', 'OLDEST', 'RELEVANT'];

    public function __construct(
        private VisiblePost $visible,
        private PostCommentRepository $comments,
        private PageOfComments $page,
    ) {
    }

    public function __invoke(ListPostComments $query): CommentPage
    {
        $post = $this->visible->to($query->postId, $query->readerId);
        $limit = $this->page->size($query->limit);
        $sort = $this->page->sort($query->sort);

        return $this->page->of(
            $this->comments->topLevelOf(
                $post->id(),
                $sort,
                $this->page->after($query->cursor),
                $limit,
            ),
            $limit,
            $query->readerId,
            $sort,
        );
    }
}
