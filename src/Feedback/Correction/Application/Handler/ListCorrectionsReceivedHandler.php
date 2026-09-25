<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\DTO\ReceivedCorrection;
use LectoresBeta\Feedback\Correction\Application\Query\ListCorrectionsReceived;
use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterHeadings;

/**
 * La bandeja del autor (`FEAT-FBK-004`).
 *
 * **Sin el contenido de ninguna.** Quien abre esta lista está decidiendo cuál
 * leer; devolver veinte correcciones enteras sería una respuesta de cientos
 * de miles de palabras para una pantalla que solo enseña títulos.
 *
 * Una corrección retenida por descubierto **aparece igual**, marcada
 * (`RN-3`): esconderla le haría creer al autor que nadie le ha corregido,
 * cuando lo que pasa es que debe créditos.
 *
 * Los títulos de los capítulos se piden **en un solo lote** al contrato de
 * `Work`. Una llamada por fila sería un N+1 escondido detrás de un contrato.
 */
final readonly class ListCorrectionsReceivedHandler
{
    private const MAX_PAGE = 100;

    public function __construct(
        private CorrectionRepository $corrections,
        private ChapterHeadings $chapters,
    ) {
    }

    /**
     * @return list<ReceivedCorrection>
     */
    public function __invoke(ListCorrectionsReceived $query): array
    {
        try {
            $ownerId = AuthorId::fromString($query->ownerId);
        } catch (InvalidValue) {
            return [];
        }

        $received = $this->corrections->receivedBy(
            $ownerId,
            self::work($query->workId),
            self::chapter($query->chapterId),
            $query->unreadOnly,
            max(1, min(self::MAX_PAGE, $query->limit)),
            max(0, $query->offset),
        );

        if ([] === $received) {
            return [];
        }

        $headings = $this->chapters->ofChapters(array_map(
            static fn (Correction $correction): string => $correction->chapterId()->value(),
            $received,
        ));

        return array_map(
            static function (Correction $correction) use ($headings): ReceivedCorrection {
                $heading = $headings[$correction->chapterId()->value()] ?? null;

                return new ReceivedCorrection(
                    $correction->id()->value(),
                    $correction->workId()->value(),
                    $correction->chapterId()->value(),
                    $heading?->title,
                    $heading->position ?? 0,
                    $correction->readerId()?->value(),
                    $correction->authorLabel(),
                    $correction->visibility()->value,
                    $correction->questionnaireVersion(),
                    ($correction->submittedAt() ?? $correction->startedAt())->format(\DATE_ATOM),
                    null !== $correction->readAt(),
                    $correction->helpful(),
                );
            },
            $received,
        );
    }

    private static function work(?string $workId): ?WorkId
    {
        try {
            return null === $workId ? null : WorkId::fromString($workId);
        } catch (InvalidValue) {
            return null;
        }
    }

    private static function chapter(?string $chapterId): ?ChapterId
    {
        try {
            return null === $chapterId ? null : ChapterId::fromString($chapterId);
        } catch (InvalidValue) {
            return null;
        }
    }
}
