<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\DTO\CorrectedChapterText;
use LectoresBeta\Feedback\Correction\Application\Query\GetCorrectedChapterText;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionLocked;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionNotFound;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterTexts;

/**
 * El capítulo tal y como lo leyó quien corrigió (`FEAT-FBK-004` `RN-8`).
 *
 * **La autorización vive aquí y no en `Work`**, y es deliberado: quién puede
 * ver ese texto depende de quién corrigió ese capítulo, y eso lo sabe este
 * contexto. `Work` tendría que preguntárselo para responder, y un contrato
 * que llama a otro contrato mientras responde es justo lo que
 * [`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)
 * prohíbe.
 *
 * Va aparte del detalle de la corrección porque son decenas de miles de
 * palabras que casi nunca hacen falta: quien abre una corrección quiere leer
 * la corrección.
 */
final readonly class GetCorrectedChapterTextHandler
{
    public function __construct(
        private CorrectionRepository $corrections,
        private ChapterTexts $chapters,
    ) {
    }

    public function __invoke(GetCorrectedChapterText $query): CorrectedChapterText
    {
        try {
            $correction = $this->corrections->ofId(CorrectionId::fromString($query->correctionId));
        } catch (InvalidValue) {
            throw CorrectionNotFound::withId($query->correctionId);
        }

        if (null === $correction || CorrectionStatus::SUBMITTED !== $correction->status()) {
            throw CorrectionNotFound::withId($query->correctionId);
        }

        $isParty = $correction->ownerId()->value() === $query->readerId
            || $correction->readerId()?->value() === $query->readerId;

        if (!$isParty) {
            throw CorrectionNotFound::withId($query->correctionId);
        }

        // Retenida por descubierto: el texto del capítulo es suyo, pero
        // servirlo aquí sería la vía para leer lo que el candado retiene —
        // basta con abrir el texto y comparar.
        if (!$correction->isReadable()) {
            throw CorrectionLocked::create();
        }

        $text = $this->chapters->ofChapter($correction->chapterId()->value(), $correction->chapterVersion())
            ?? throw CorrectionNotFound::withId($query->correctionId);

        return new CorrectedChapterText(
            $correction->id()->value(),
            $text->chapterId,
            $text->title,
            $text->contentHtml,
            $text->wordCount,
            $text->version,
            $text->isCurrentVersion,
        );
    }
}
