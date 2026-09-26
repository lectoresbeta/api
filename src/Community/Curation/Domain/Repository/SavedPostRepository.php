<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Domain\Repository;

use LectoresBeta\Community\Curation\Domain\Entity\SavedPost;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

interface SavedPostRepository
{
    public function save(SavedPost $saved): void;

    public function remove(MemberId $memberId, PostId $postId): void;

    public function has(MemberId $memberId, PostId $postId): bool;

    /**
     * Qué ha guardado esta persona.
     *
     * Devuelve **identificadores** y no publicaciones a propósito: la lista de
     * guardados es el muro con un filtro (`FEAT-COM-021`), así que lo que
     * hace falta aquí es el filtro. Devolver entidades sería saltarse las
     * reglas de audiencia que el muro aplica en la consulta.
     *
     * @return list<string>
     */
    public function savedBy(MemberId $memberId): array;
}
