<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\DTO;

/**
 * Si sigo a esta persona, que es lo que el botón del perfil necesita saber
 * (`FEAT-COM-010`).
 *
 * **Solo sobre mí.** A quién sigue otra persona, y quién la sigue a ella, son
 * las listas de `FEAT-COM-027`, y si son públicas sigue sin decidirse
 * (`CM-14`): una operación que se llama «seguir» no puede decidir de paso
 * quién audita el grafo social.
 */
final readonly class SubscriptionState
{
    public function __construct(
        public bool $subscribed,
        public ?\DateTimeImmutable $subscribedAt,
    ) {
    }
}
