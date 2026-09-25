<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Repository;

use LectoresBeta\Community\Interaction\Domain\Entity\PostRepost;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostFilters;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface PostRepostRepository
{
    public function save(PostRepost $repost): void;

    public function remove(PostRepost $repost): void;

    public function between(MemberId $memberId, PostId $postId): ?PostRepost;

    /**
     * Los reposts que entran en el muro de quien mira.
     *
     * **La audiencia se comprueba sobre el original**, aquí y en la consulta
     * de publicaciones, porque un repost no la amplía. Es la mitad que se
     * olvidaría: basta con servir los reposts sin volver a mirar el original
     * para publicar contenido restringido.
     *
     * @param list<string> $followedAuthorIds
     * @param list<string> $hiddenAuthorIds   con quién hay bloqueo, que aquí
     *                                        vale para **los dos**: quien
     *                                        repostea y el autor original
     * @param ?MemberId    $onlyMemberId      el muro **de una persona**
     *                                        (`FEAT-COM-026`): aquí filtra por quien
     *                                        repostea, no por quien escribió. Lo que
     *                                        alguien saca a su muro es suyo aunque el
     *                                        texto sea de otro, que es lo que la
     *                                        cabecera del repost dice
     *
     * @return list<PostRepost> con una fila de más para saber si hay página
     *                          siguiente
     */
    public function wallFor(
        MemberId $readerId,
        array $followedAuthorIds,
        array $hiddenAuthorIds,
        ?Cursor $after,
        int $limit,
        ?MemberId $onlyMemberId = null,
        ?PostFilters $filters = null,
    ): array;
}
