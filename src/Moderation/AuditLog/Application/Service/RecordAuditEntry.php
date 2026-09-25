<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Application\Service;

use LectoresBeta\Moderation\AuditLog\Domain\Entity\AuditEntry;
use LectoresBeta\Moderation\AuditLog\Domain\Repository\AuditEntryRepository;
use LectoresBeta\Moderation\AuditLog\Domain\ValueObject\AuditEntryId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Domain\Clock\Clock;

/**
 * Dejar constancia de lo que hace un moderador (`FEAT-MOD-004` `RN-6`).
 *
 * **Con su identidad, aunque las partes no la conozcan.** Quien resuelve una
 * reclamación no da la cara ante los implicados —eso los expondría— pero sí
 * ante la plataforma: un poder que se ejerce sin dejar nombre es un poder que
 * nadie puede revisar después.
 *
 * Se anota en la misma transacción que el cambio, nunca después: un registro
 * que se puede perder mientras el efecto se conserva es peor que no tenerlo,
 * porque invita a confiar en él.
 */
final readonly class RecordAuditEntry
{
    public function __construct(
        private AuditEntryRepository $entries,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    public function of(
        PartyId $actorId,
        string $action,
        string $targetType,
        string $targetId,
        ?string $reason = null,
        array $payload = [],
    ): void {
        $this->entries->add(new AuditEntry(
            AuditEntryId::generate(),
            $actorId,
            $action,
            $targetType,
            $targetId,
            $this->clock->now(),
            $reason,
            $payload,
        ));
    }
}
