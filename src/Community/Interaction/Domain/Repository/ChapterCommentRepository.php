<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Repository;

use LectoresBeta\Community\Interaction\Domain\Entity\ChapterComment;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterCommentId;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface ChapterCommentRepository
{
    public function save(ChapterComment $comment): void;

    /**
     * Un comentario vivo. Uno eliminado responde `null`: ha dejado de existir
     * para todo el mundo, su autor incluido.
     */
    public function ofId(ChapterCommentId $commentId): ?ChapterComment;

    /**
     * Los comentarios **de primer nivel** de un capítulo, del más reciente al
     * más antiguo.
     *
     * Las respuestas no salen aquí: cuelgan de su comentario y se piden
     * aparte, igual que en el muro. Mezclarlas obligaría al cliente a
     * reconstruir el árbol a partir de una lista plana.
     *
     * @return list<ChapterComment> con una fila de más para saber si hay
     *                              página siguiente
     */
    public function topLevelOf(ChapterId $chapterId, ?Cursor $after, int $limit): array;

    /**
     * Las respuestas de un comentario, **de la más antigua a la más
     * reciente**: un hilo se lee en el orden en que se dijo.
     *
     * @return list<ChapterComment>
     */
    public function repliesOf(ChapterCommentId $rootId, ?Cursor $after, int $limit): array;

    /**
     * Todas las respuestas vivas de un comentario, sin paginar, para
     * retirarlas con su raíz.
     *
     * @return list<ChapterComment>
     */
    public function allRepliesOf(ChapterCommentId $rootId): array;
}
