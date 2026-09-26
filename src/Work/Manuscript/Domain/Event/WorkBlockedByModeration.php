<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Se ha bloqueado una obra o uno de sus capítulos (`FEAT-MOD-003`).
 *
 * Lo publica `Work`, que es quien ejecuta el bloqueo, y lo escuchan los
 * contextos a los que les cambia algo: `Notification` avisa al autor,
 * `Community` lo retira de muros y recomendaciones, `Credits` recalcula la
 * corregibilidad.
 *
 * `scope` distingue las dos cosas que la ficha separa a propósito: bloquear
 * una novela entera por un capítulo destruiría el trabajo de los otros
 * veintinueve y las correcciones que otros escribieron sobre ellos.
 *
 * `reason` es **el tipo de la reclamación estimada**, no la motivación
 * interna del moderador, que no sale del expediente. Al tercer capítulo
 * bloqueado la obra entera cae, y entonces el motivo es el umbral.
 */
final readonly class WorkBlockedByModeration implements IntegrationEvent
{
    public const CHAPTER_THRESHOLD = 'BLOCKED_CHAPTER_THRESHOLD';

    public function __construct(
        private EventId $eventId,
        private string $workId,
        private string $authorId,
        private string $title,
        private string $scope,
        private ?string $chapterId,
        private string $reason,
        private ?string $claimId,
        private \DateTimeImmutable $blockedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkBlockedByModeration';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->blockedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            // El título viaja porque el correo tiene que decirle al autor
            // **qué** se ha bloqueado, y un identificador no se lo dice. Es
            // suyo y va dirigido a él: no hay nada que ocultar aquí.
            'title' => $this->title,
            'scope' => $this->scope,
            'chapterId' => $this->chapterId,
            'reason' => $this->reason,
            'claimId' => $this->claimId,
            'blockedAt' => $this->blockedAt->format(\DATE_ATOM),
        ];
    }
}
