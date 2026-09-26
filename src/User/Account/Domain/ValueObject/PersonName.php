<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * The name a person shows publicly (`FEAT-USR-022`, `OB-2`).
 *
 * It is not unique and it is not identity: two people called the same are
 * told apart by their avatar and their `@username` (`FEAT-USR-022`,
 * confirmed 2026-09-24).
 */
final readonly class PersonName implements \Stringable
{
    private const MAX_LENGTH = 80;

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
            throw InvalidValue::because('The name cannot be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('The name cannot exceed %d characters.', self::MAX_LENGTH));
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
