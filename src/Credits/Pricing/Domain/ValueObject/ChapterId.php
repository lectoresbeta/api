<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * A chapter, as Credits knows it: an identifier that arrived in an event.
 * This context never reads a word of its content.
 */
final readonly class ChapterId extends Uuid
{
}
