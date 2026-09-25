<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * El apoyo a un comentario, o a una respuesta (`FEAT-COM-030`).
 *
 * Gemela de `PostLike` y **no la misma tabla con un tipo de objetivo**. Un
 * objetivo polimórfico obliga a que cada consulta sepa qué es cada fila, no
 * admite clave foránea, y convierte «borra los apoyos de esto» en algo que
 * hay que acordarse de filtrar. Dos tablas con la misma forma cuestan una
 * clase y se leen solas.
 *
 * Vale igual para un comentario de primer nivel y para una respuesta: son la
 * misma entidad con un `parentCommentId`, así que aquí no hay nada que
 * distinguir (`RN-5`).
 */
class PostCommentLike
{
    private string $commentId;

    private string $memberId;

    private \DateTimeImmutable $likedAt;

    public function __construct(PostCommentId $commentId, MemberId $memberId, \DateTimeImmutable $now)
    {
        $this->commentId = $commentId->value();
        $this->memberId = $memberId->value();
        $this->likedAt = $now;
    }

    public function commentId(): PostCommentId
    {
        return PostCommentId::fromString($this->commentId);
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }
}
