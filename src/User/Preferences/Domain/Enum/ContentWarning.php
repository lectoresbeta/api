<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Enum;

/**
 * The closed catalogue of sensitive-content labels (`FEAT-WRK-017`, `W-20`),
 * as this context reads it: what a person chooses not to see
 * (`FEAT-USR-043`).
 *
 * `Work` has its own copy, because there the same five values answer a
 * different question — what a work declares it contains. Duplicating the enum
 * is deliberate (`AGENTS.md`): sharing it would tie the reader's filter to the
 * author's declaration.
 *
 * `ADULTS_ONLY` is not here: it is not a preference. It is filtered by age
 * whatever the person chooses (`FEAT-USR-043` `RN-4`).
 */
enum ContentWarning: string
{
    case SEXUAL_CONTENT = 'SEXUAL_CONTENT';
    case GRAPHIC_VIOLENCE = 'GRAPHIC_VIOLENCE';
    case SELF_HARM = 'SELF_HARM';
    case SUBSTANCE_USE = 'SUBSTANCE_USE';
    case STRONG_LANGUAGE = 'STRONG_LANGUAGE';
}
