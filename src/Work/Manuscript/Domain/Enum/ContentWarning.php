<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Enum;

/**
 * What a work declares it contains (`FEAT-WRK-017`, `W-20`).
 *
 * A closed catalogue, not free text: labels that cannot be filtered on are
 * not labels (`RN-4`). Five values, descriptive rather than judgemental.
 *
 * `User` keeps its own copy for the reader's filter. Duplicating the enum is
 * deliberate: the author's declaration and the reader's preference are
 * different things that happen to share a vocabulary.
 *
 * Being adults-only is **not** one of these. It is a separate flag on the
 * work, because a text can be adults-only by accumulation without any single
 * label being decisive.
 */
enum ContentWarning: string
{
    case SEXUAL_CONTENT = 'SEXUAL_CONTENT';
    case GRAPHIC_VIOLENCE = 'GRAPHIC_VIOLENCE';
    case SELF_HARM = 'SELF_HARM';
    case SUBSTANCE_USE = 'SUBSTANCE_USE';
    case STRONG_LANGUAGE = 'STRONG_LANGUAGE';
}
