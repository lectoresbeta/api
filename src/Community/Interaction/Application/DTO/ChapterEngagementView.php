<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\DTO;

/**
 * Las cifras sociales de la cabecera de un capítulo (`FEAT-COM-036`, `R-1`).
 *
 * **Sin lecturas.** La maqueta enseña tres métricas y esta trae dos: qué
 * cuenta como «lectura» sigue sin definirse (`H-3`), y un contador que nadie
 * sabe qué mide es peor que no tenerlo.
 */
final readonly class ChapterEngagementView
{
    public function __construct(
        public string $chapterId,
        public int $likeCount,
        public int $commentCount,
        public bool $likedByViewer,
    ) {
    }
}
