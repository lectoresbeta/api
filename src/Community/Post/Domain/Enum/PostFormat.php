<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Enum;

/**
 * How a post is presented. `VIDEO` is listed but not decided (`C-2`,
 * `CM-17`): transcoding and storage have a cost nobody has signed off.
 */
enum PostFormat: string
{
    case TEXT = 'TEXT';
    case IMAGE = 'IMAGE';
    case VIDEO = 'VIDEO';
    case LINK = 'LINK';
    case WORK = 'WORK';
}
