<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Enum;

/**
 * Which of the two private threads a message belongs to (`FEAT-MOD-009`).
 *
 * The conversation is star-shaped: the moderator talks to each party
 * separately and **the parties never see each other** (`RN-7`). This column
 * is what separates the threads inside one file, and it is where the whole
 * privacy of the feature rests — worth covering with tests that deliberately
 * try to read the other thread.
 */
enum ThreadParty: string
{
    case REPORTER = 'REPORTER';
    case SUBJECT = 'SUBJECT';
}
