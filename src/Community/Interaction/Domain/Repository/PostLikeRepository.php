<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Repository;

use LectoresBeta\Community\Interaction\Domain\Entity\PostLike;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

interface PostLikeRepository
{
    public function save(PostLike $like): void;

    public function remove(PostLike $like): void;

    public function between(MemberId $memberId, PostId $postId): ?PostLike;

    /**
     * Cuáles de estas publicaciones ha apoyado quien mira, para que el botón
     * salga resaltado (`RN-7`).
     *
     * De golpe y no una por tarjeta: un muro de veinte entradas serían veinte
     * consultas para pintar veinte corazones.
     *
     * @param list<string> $postIds
     *
     * @return list<string>
     */
    public function likedAmong(MemberId $memberId, array $postIds): array;

    /**
     * Los apoyos de una publicación que se borra (`RN-9`). No quedan filas
     * apuntando a nada.
     */
    public function removeAllOf(PostId $postId): void;
}
