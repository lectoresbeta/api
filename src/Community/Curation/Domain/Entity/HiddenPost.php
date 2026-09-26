<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * «Esto no me interesa» (`FEAT-COM-022`).
 *
 * La publicación sigue existiendo para todo el mundo: lo que desaparece es la
 * tarjeta, y solo del muro de quien la ocultó.
 *
 * **No se avisa al autor y no se cuenta.** Un contador de «cuánta gente ha
 * ocultado esto» sería una métrica de rechazo entregada a quien escribió el
 * texto.
 */
class HiddenPost
{
    private string $memberId;

    private string $postId;

    private \DateTimeImmutable $hiddenAt;

    public function __construct(MemberId $memberId, PostId $postId, \DateTimeImmutable $now)
    {
        $this->memberId = $memberId->value();
        $this->postId = $postId->value();
        $this->hiddenAt = $now;
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }

    public function postId(): PostId
    {
        return PostId::fromString($this->postId);
    }

    public function hiddenAt(): \DateTimeImmutable
    {
        return $this->hiddenAt;
    }
}
