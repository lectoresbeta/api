<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Repository;

use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Entity\PostAttachment;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostFilters;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Community\Post\Domain\ValueObject\WallCuration;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface PostRepository
{
    public function save(Post $post): void;

    public function attach(PostAttachment $attachment): void;

    /**
     * Una publicación viva. Una eliminada responde `null`, porque para todo
     * el mundo —su autor incluido— ha dejado de existir (`FEAT-COM-002`
     * `RN-13`).
     */
    public function ofId(PostId $postId): ?Post;

    /**
     * El muro de quien mira (`FEAT-COM-001`).
     *
     * **El filtro de audiencia va en la consulta, no después.** Traer lo que
     * no se puede ver para descartarlo en memoria rompe la paginación —una
     * página de veinte devolvería doce— y deja el dato viajando por dentro
     * del servidor, a un `dump` de distancia de salir.
     *
     * @param list<string>  $followedAuthorIds a quién sigue quien mira, que es
     *                                         lo que abre las publicaciones
     *                                         `FOLLOWERS`
     * @param list<string>  $hiddenAuthorIds   con quién hay un bloqueo, en
     *                                         cualquiera de las dos
     *                                         direcciones
     * @param ?MemberId     $onlyAuthorId      el muro **de una persona**
     *                                         (`FEAT-COM-026`), o `null` para el muro
     *                                         general. Es un filtro y no una consulta
     *                                         aparte: las reglas de audiencia y de
     *                                         bloqueo son exactamente las mismas, y
     *                                         escribirlas dos veces sería dejar que
     *                                         una de las dos se quedase atrás
     * @param ?PostFilters  $filters           lo que el usuario ha acotado
     *                                         (`FEAT-COM-009`), o `null` para el muro
     *                                         sin filtrar
     * @param ?WallCuration $curation          lo que quien mira decidió sobre su
     *                                         propio muro: lo que escondió
     *                                         (`FEAT-COM-022`) y, en la lista de
     *                                         guardados, a qué se restringe
     *                                         (`FEAT-COM-021`)
     *
     * @return list<Post> con una fila de más para saber si hay página
     *                    siguiente
     */
    public function wallFor(
        MemberId $readerId,
        array $followedAuthorIds,
        array $hiddenAuthorIds,
        ?Cursor $after,
        int $limit,
        ?MemberId $onlyAuthorId = null,
        ?PostFilters $filters = null,
        ?WallCuration $curation = null,
    ): array;

    /**
     * Estas publicaciones, indexadas por identificador.
     *
     * Las eliminadas simplemente no están, que es lo mismo que responde
     * `ofId`.
     *
     * @param list<string> $postIds
     *
     * @return array<string, Post>
     */
    public function ofIds(array $postIds): array;

    /**
     * Los adjuntos de estas publicaciones, indexados por publicación.
     *
     * Por lotes porque se pinta una página entera: uno por tarjeta sería un
     * N+1 escondido detrás de un método con buen nombre.
     *
     * @param list<string> $postIds
     *
     * @return array<string, PostAttachment>
     */
    public function attachmentsOf(array $postIds): array;
}
