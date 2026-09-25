<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\DTO\ChapterEngagementView;
use LectoresBeta\Community\Interaction\Application\Query\GetChapterEngagement;
use LectoresBeta\Community\Interaction\Application\Service\ReadableChapter;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterEngagementRepository;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterLikeRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Las cifras de la cabecera de un capítulo (`FEAT-COM-036`, `R-1`).
 *
 * **Endpoint aparte del capítulo**, y no un par de campos más en
 * `GET /chapters/{id}`. No es una elección de estilo: el capítulo lo sirve
 * `Work` y estas cifras son de `Community`, y meterlas en la misma respuesta
 * obligaría a uno de los dos contextos a conocer al otro. El precio es una
 * segunda llamada del cliente; el precio de lo otro era la frontera.
 *
 * `likedByViewer` va aquí porque es lo que decide si el corazón sale
 * resaltado, y sin él la pantalla necesitaría una tercera llamada.
 */
final readonly class GetChapterEngagementHandler
{
    public function __construct(
        private ReadableChapter $reachable,
        private ChapterEngagementRepository $engagements,
        private ChapterLikeRepository $likes,
    ) {
    }

    public function __invoke(GetChapterEngagement $query): ChapterEngagementView
    {
        $access = $this->reachable->seenBy($query->chapterId, $query->readerId);
        $chapterId = ChapterId::fromString($access->chapterId);
        $engagement = $this->engagements->ofChapter($chapterId);

        return new ChapterEngagementView(
            $access->chapterId,
            // Sin fila significa que nadie lo ha tocado, que se lee como
            // ceros: sembrar una fila por capítulo para enseñar dos ceros
            // sería mantener una tabla del tamaño del catálogo.
            $engagement?->likeCount() ?? 0,
            $engagement?->commentCount() ?? 0,
            null !== $this->likes->between($chapterId, MemberId::fromString($query->readerId)),
        );
    }
}
