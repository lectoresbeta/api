<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Application\DTO;

/**
 * Lo que se devuelve al presentar una reclamación.
 *
 * **No dice qué va a pasar**, porque no va a pasar nada todavía (`RN-1`):
 * confirma el registro y da un identificador con el que seguirla.
 *
 * `alreadyExisted` distingue el registro nuevo del reintento, que es lo que
 * permite al cliente no decir «gracias por tu denuncia» dos veces por la
 * misma.
 */
final readonly class SubmittedClaim
{
    public function __construct(
        public string $claimId,
        public string $status,
        public bool $alreadyExisted,
    ) {
    }
}
