<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Entity;

use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;

/**
 * Que alguien sigue a alguien, **copiado aquí** (`FEAT-NOT-004`).
 *
 * Es la tercera copia del mismo grafo —`Community` lo posee, `User` lo copia
 * para resolver audiencias `FOLLOWERS`— y merece justificarse, porque una
 * copia de más es un sitio de más donde envejecer.
 *
 * La razón es la que da `AGENTS.md`: preferir el hecho asíncrono a la llamada
 * síncrona cuando la hay. Un reparto de avisos **no tiene a nadie esperando
 * al otro lado**, así que no necesita preguntar ahora; y un contrato que
 * entregara la lista de seguidores de alguien sería justo lo que
 * `SubscriptionCounts` se negó a ser, con el agravante de que aquí se pediría
 * por páginas y en mitad del consumo de un evento.
 *
 * Lleva **lo justo para repartir**: el par y la fecha. Ni nombres, ni
 * perfiles, ni el identificador de la suscripción. Una copia que guardase más
 * acabaría siendo un segundo modelo del seguimiento, con dos dueños.
 *
 * La identidad es el par, lo que la hace idempotente sin esfuerzo: volver a
 * procesar el mismo hecho escribe la misma fila.
 */
class AuthorFollower
{
    private string $authorId;

    private string $followerId;

    private \DateTimeImmutable $followedAt;

    public function __construct(RecipientId $authorId, RecipientId $followerId, \DateTimeImmutable $followedAt)
    {
        $this->authorId = $authorId->value();
        $this->followerId = $followerId->value();
        $this->followedAt = $followedAt;
    }

    public function authorId(): RecipientId
    {
        return RecipientId::fromString($this->authorId);
    }

    public function followerId(): RecipientId
    {
        return RecipientId::fromString($this->followerId);
    }

    /**
     * Un hecho que llega tarde no adelanta la fecha: el orden de entrega no
     * está garantizado, y la fecha es la del seguimiento, no la de su
     * llegada.
     */
    public function keepEarliest(\DateTimeImmutable $followedAt): void
    {
        if ($followedAt < $this->followedAt) {
            $this->followedAt = $followedAt;
        }
    }
}
