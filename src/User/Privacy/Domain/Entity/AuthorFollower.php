<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Que alguien sigue a alguien, **copiado aquí** (`FEAT-COM-010`).
 *
 * No es un agregado: es una proyección de un hecho que pertenece a
 * `Community`, mantenida por los hechos `AuthorSubscribed` y
 * `AuthorUnsubscribed`. Existe por una regla y no por comodidad:
 * `CheckAuthorAudience` es un contrato publicado, y
 * [`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)
 * prohíbe que un contrato llame al de otro contexto mientras responde. Sin
 * esta copia, `User` tendría que preguntarle a `Community` en mitad de una
 * respuesta a `Work`.
 *
 * Por eso lleva lo justo para responder «¿me sigue?»: ni el identificador de
 * la suscripción ni su histórico. Una copia que guardase más acabaría siendo
 * un segundo modelo del seguimiento, con dos dueños.
 *
 * La identidad es el par, lo que la hace idempotente sin esfuerzo: volver a
 * procesar el mismo hecho escribe la misma fila.
 */
class AuthorFollower
{
    private string $authorId;

    private string $followerId;

    private \DateTimeImmutable $followedAt;

    public function __construct(UserId $authorId, UserId $followerId, \DateTimeImmutable $followedAt)
    {
        $this->authorId = $authorId->value();
        $this->followerId = $followerId->value();
        $this->followedAt = $followedAt;
    }

    public function authorId(): UserId
    {
        return UserId::fromString($this->authorId);
    }

    public function followerId(): UserId
    {
        return UserId::fromString($this->followerId);
    }

    public function followedAt(): \DateTimeImmutable
    {
        return $this->followedAt;
    }

    /**
     * Un hecho que llega tarde no adelanta la fecha. El orden de entrega no
     * está garantizado, y la fecha del seguimiento es la del hecho, no la de
     * su llegada.
     */
    public function keepEarliest(\DateTimeImmutable $followedAt): void
    {
        if ($followedAt < $this->followedAt) {
            $this->followedAt = $followedAt;
        }
    }
}
