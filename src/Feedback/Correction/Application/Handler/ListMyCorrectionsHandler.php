<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\DTO\MyCorrection;
use LectoresBeta\Feedback\Correction\Application\Query\ListMyCorrections;
use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionEarningRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionReplyRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterHeadings;

/**
 * «Mis correcciones» (`FEAT-FBK-010`).
 *
 * La simétrica de «Mis reclamaciones», y existe por lo mismo: **corregir no
 * puede ser escribir en un buzón**. Quien se molesta en responder a un
 * cuestionario necesita poder ver qué escribió, qué ganó y qué hizo el autor
 * con ello.
 *
 * Incluye **los borradores propios**, distinguidos: un borrador es trabajo
 * empezado, y quien lo dejó a medias necesita encontrarlo.
 *
 * Nunca hay forma de listar las de otra persona. El **contador** de
 * correcciones es público y la **lista** no
 * ([`FEAT-USR-014`](../../../../../docs/features/user/FEAT-USR-014-view-public-profile.md)
 * `U-17`), y por eso el contrato publicado de este contexto devuelve una
 * cifra y nunca correcciones.
 */
final readonly class ListMyCorrectionsHandler
{
    private const MAX_PAGE = 100;

    public function __construct(
        private CorrectionRepository $corrections,
        private CorrectionEarningRepository $earnings,
        private CorrectionReplyRepository $replies,
        private ChapterHeadings $chapters,
    ) {
    }

    /**
     * @return list<MyCorrection>
     */
    public function __invoke(ListMyCorrections $query): array
    {
        try {
            $readerId = ReaderId::fromString($query->readerId);
        } catch (InvalidValue) {
            return [];
        }

        $mine = $this->corrections->writtenBy(
            $readerId,
            self::work($query->workId),
            CorrectionStatus::tryFrom(strtoupper((string) $query->status)),
            max(1, min(self::MAX_PAGE, $query->limit)),
            max(0, $query->offset),
        );

        if ([] === $mine) {
            return [];
        }

        $ids = array_map(static fn (Correction $correction) => $correction->id(), $mine);
        $earnings = $this->earnings->ofCorrections($ids);
        $replies = $this->replies->ofCorrections($ids);
        $headings = $this->chapters->ofChapters(array_map(
            static fn (Correction $correction): string => $correction->chapterId()->value(),
            $mine,
        ));

        return array_map(
            static function (Correction $correction) use ($earnings, $replies, $headings): MyCorrection {
                $id = $correction->id()->value();
                $heading = $headings[$correction->chapterId()->value()] ?? null;

                return new MyCorrection(
                    $id,
                    $correction->workId()->value(),
                    $correction->chapterId()->value(),
                    $heading->title ?? null,
                    $heading->position ?? 0,
                    $heading->workTitle ?? '',
                    $correction->status()->value,
                    $correction->submittedAt()?->format(\DATE_ATOM),
                    // Nulo mientras no ha llegado, nunca cero: un cero es una
                    // afirmación falsa sobre el trabajo de alguien.
                    isset($earnings[$id]) ? $earnings[$id]->credits() : null,
                    $correction->helpful(),
                    isset($replies[$id]),
                    null !== $correction->tipAmount(),
                );
            },
            $mine,
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
}
