<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Service;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Domain\Enum\ThreadParty;
use LectoresBeta\Moderation\Review\Domain\Exception\ClaimMessageRefused;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Cuál de los dos hilos le corresponde a quien pregunta (`FEAT-MOD-009`).
 *
 * **La conversación tiene forma de estrella, no de sala**: cada parte tiene
 * un hilo privado con el moderador y no sabe qué dice la otra, ni siquiera si
 * hay otra. No es una restricción técnica sino la razón de ser del mecanismo
 * — poner a denunciante y denunciado a discutir crearía el conflicto que la
 * moderación existe para evitar.
 *
 * Preguntarlo así —«qué hilo es el tuyo»— y no «dame este hilo» es lo que
 * impide que alguien pida el ajeno. Con un identificador de hilo en la ruta,
 * leer el de la otra parte sería cambiar una palabra en la dirección.
 */
final readonly class ClaimThread
{
    public function __construct(private ClaimRepository $claims)
    {
    }

    /**
     * @return array{claim: Claim, party: ThreadParty}
     */
    public function of(string $claimId, string $readerId): array
    {
        try {
            $claim = $this->claims->ofId(ClaimId::fromString($claimId));
            $reader = PartyId::fromString($readerId);
        } catch (InvalidValue) {
            throw ClaimMessageRefused::claimNotFound();
        }

        if (null === $claim) {
            throw ClaimMessageRefused::claimNotFound();
        }

        if ($claim->reporterId()->value() === $reader->value()) {
            return ['claim' => $claim, 'party' => ThreadParty::REPORTER];
        }

        if ($claim->subjectId()?->value() === $reader->value()) {
            return ['claim' => $claim, 'party' => ThreadParty::SUBJECT];
        }

        // Para quien no es parte, la reclamación **no existe**. Un permiso
        // denegado le confirmaría que hay un expediente abierto, que ya es
        // información sobre otras personas.
        throw ClaimMessageRefused::claimNotFound();
    }
}
