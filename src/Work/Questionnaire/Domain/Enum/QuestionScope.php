<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Domain\Enum;

/**
 * Whether a question is asked about every chapter or only at the end
 * (`FEAT-WRK-014`, `W-17`).
 *
 * «Did the ending work?» makes no sense on chapter three of a novel, and
 * charging the reader of chapter three for answering it makes even less.
 */
enum QuestionScope: string
{
    case EVERY_CHAPTER = 'EVERY_CHAPTER';
    case LAST_CHAPTER = 'LAST_CHAPTER';
}
