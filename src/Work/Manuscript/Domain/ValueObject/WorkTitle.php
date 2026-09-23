<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

final readonly class WorkTitle implements \Stringable
{
    private const MAX_LENGTH = 180;

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw InvalidValue::because('The title cannot be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('The title cannot exceed %d characters.', self::MAX_LENGTH));
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
