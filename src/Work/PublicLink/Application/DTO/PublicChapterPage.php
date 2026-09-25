<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\DTO;

use LectoresBeta\Work\Chapter\Application\Contract\BriefQuestion;

/**
 * Un capítulo leído por el enlace, con lo que su autor pregunta sobre él.
 *
 * Las preguntas vienen ya filtradas para **este** capítulo: una de alcance
 * `LAST_CHAPTER` no aparece en el primero (`FEAT-WRK-014` `W-17`). El filtro
 * lo hace `Work`, que es el único que sabe cuál es el último.
 *
 * `wouldBeWorth` es lo que esta corrección valdría si la escribiera alguien
 * con cuenta. Es la cifra del mensaje de captación de `FEAT-FBK-008`, y es
 * **informativa**: por este camino no se mueve ni un crédito.
 */
final readonly class PublicChapterPage
{
    /**
     * @param list<BriefQuestion> $questions
     */
    public function __construct(
        public string $chapterId,
        public string $workId,
        public string $workTitle,
        public int $position,
        public ?string $title,
        public string $contentHtml,
        public int $wordCount,
        public int $chapterVersion,
        public int $questionnaireVersion,
        public ?int $wouldBeWorth,
        public array $questions,
    ) {
    }
}
