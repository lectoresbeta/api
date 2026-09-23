<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Enum;

/**
 * The reason given, from a closed list so that claims can be counted and
 * compared.
 *
 * The feedback reasons are the delicate ones. A claim of `NO_VALUE` must not
 * become a way of not paying for a correction that was simply harsh: if the
 * system cannot tell a hard critique from a fraudulent one, correctors will
 * learn to write praise, which is the opposite of the product.
 */
enum ClaimReason: string
{
    case NO_VALUE = 'NO_VALUE';
    case TOO_SHORT = 'TOO_SHORT';
    case OFF_TOPIC = 'OFF_TOPIC';
    case OFFENSIVE = 'OFFENSIVE';

    case SEXUAL_CONTENT = 'SEXUAL_CONTENT';
    case GRAPHIC_VIOLENCE = 'GRAPHIC_VIOLENCE';
    case HATE_SPEECH = 'HATE_SPEECH';
    case PLAGIARISM = 'PLAGIARISM';
    case MISSING_CONTENT_WARNING = 'MISSING_CONTENT_WARNING';
    case HARASSMENT = 'HARASSMENT';
    case SPAM = 'SPAM';
    case OTHER = 'OTHER';
}
