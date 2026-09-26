<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Enum;

enum MessageAuthorType: string
{
    case MODERATOR = 'MODERATOR';
    case PARTY = 'PARTY';
}
