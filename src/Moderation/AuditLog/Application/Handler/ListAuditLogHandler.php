<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Application\Handler;

use LectoresBeta\Moderation\AuditLog\Application\DTO\AuditEntryView;
use LectoresBeta\Moderation\AuditLog\Application\Query\ListAuditLog;
use LectoresBeta\Moderation\AuditLog\Domain\Entity\AuditEntry;
use LectoresBeta\Moderation\AuditLog\Domain\Repository\AuditEntryRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El registro de auditoría (`FEAT-MOD-007`).
 *
 * **Lo lee solo un administrador**, y quién puede leerlo lo decide la ruta.
 * Un moderador no audita a sus compañeros: el registro existe para que el
 * poder que se ejerce sobre los usuarios sea revisable por quien responde de
 * la plataforma, no para vigilancia horizontal dentro del equipo. Saberse
 * observado por los iguales cambia cómo se decide, y lo cambia hacia decidir
 * lo que no genera preguntas en vez de lo correcto.
 *
 * **Consultarlo no se anota** (`RN-9`): anotar cada lectura convertiría el
 * registro en su propio ruido.
 */
final readonly class ListAuditLogHandler
{
    private const MAX_PAGE = 100;

    public function __construct(private AuditEntryRepository $entries)
    {
    }

    /**
     * @return list<AuditEntryView>
     */
    public function __invoke(ListAuditLog $query): array
    {
        return array_map(
            static fn (AuditEntry $entry): AuditEntryView => new AuditEntryView(
                $entry->id()->value(),
                $entry->actorId()->value(),
                $entry->action(),
                $entry->targetType(),
                $entry->targetId(),
                $entry->reason(),
                $entry->payload(),
                $entry->occurredAt()->format(\DATE_ATOM),
            ),
            $this->entries->search(
                self::party($query->actorId),
                self::trimmed($query->action),
                self::trimmed($query->targetType),
                self::trimmed($query->targetId),
                self::moment($query->from),
                self::moment($query->to),
                max(1, min(self::MAX_PAGE, $query->limit)),
                max(0, $query->offset),
            ),
        );
    }

    private static function party(?string $actorId): ?PartyId
    {
        try {
            return null === $actorId ? null : PartyId::fromString($actorId);
        } catch (InvalidValue) {
            return null;
        }
    }

    private static function trimmed(?string $value): ?string
    {
        $text = trim((string) $value);

        return '' === $text ? null : $text;
    }

    private static function moment(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
