<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Enum;

enum ReviewDecision: string
{
    case UPHELD = 'UPHELD';
    case REJECTED = 'REJECTED';
}
