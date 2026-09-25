<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Repository;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Review\Domain\Entity\ClaimMessage;
use LectoresBeta\Moderation\Review\Domain\Enum\ThreadParty;

interface ClaimMessageRepository
{
    public function add(ClaimMessage $message): void;

    /**
     * **Un hilo**, el de esa parte, de lo más antiguo a lo más reciente.
     *
     * Fíjate en que el hilo es un parámetro y no un filtro posterior: traer
     * los dos y quedarse con uno dejaría el mensaje de la otra parte viajando
     * por dentro del servidor, a un volcado de distancia de salir. Aquí eso
     * no es una imprudencia teórica — es exactamente lo que esta
     * funcionalidad existe para impedir.
     *
     * @return list<ClaimMessage>
     */
    public function thread(ClaimId $claimId, ThreadParty $party): array;

    /**
     * Si el moderador ya abrió ese hilo.
     *
     * Es lo que decide si una parte puede escribir: no abre conversación por
     * su cuenta (`RN-2`).
     */
    public function isOpen(ClaimId $claimId, ThreadParty $party): bool;
}
