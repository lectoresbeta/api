<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Application\DTO;

/**
 * Una entrada del registro, tal y como la lee un administrador.
 *
 * Lleva **la identidad del actor**, aunque las partes implicadas no la
 * conozcan: un poder que se ejerce sin dejar nombre es un poder que nadie
 * puede revisar después.
 *
 * Y lleva la **motivación**, que es material interno del expediente y no sale
 * por ninguna otra puerta.
 */
final readonly class AuditEntryView
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $entryId,
        public string $actorId,
        public string $action,
        public string $targetType,
        public string $targetId,
        public ?string $reason,
        public array $payload,
        public string $occurredAt,
    ) {
    }
}
