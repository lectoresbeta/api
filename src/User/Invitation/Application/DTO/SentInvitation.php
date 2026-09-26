<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\DTO;

/**
 * Una invitación mandada, vista por quien la mandó (`FEAT-USR-018`).
 *
 * **No dice si se cobró por ella.** Lo que se pagó y a quién es asunto de
 * `Credits`, y preguntárselo para pintar esta lista sería abrir una puerta
 * entre contextos para un adorno (`decision:0002`). El saldo y su historial
 * ya cuentan esa parte, y la cuentan mejor.
 *
 * Tampoco lleva el identificador de quien se registró: el invitador tiene
 * derecho a saber que su invitación fue aceptada, no a que le entreguen la
 * cuenta de esa persona.
 */
final readonly class SentInvitation
{
    public function __construct(
        public string $invitationId,
        public ?string $email,
        public bool $accepted,
        public string $sentAt,
        public ?string $acceptedAt,
    ) {
    }
}
