<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * An email address, always normalised to lowercase.
 *
 * Normalising here and not at the HTTP boundary is what makes
 * `Usuario@Ejemplo.com` and `usuario@ejemplo.com` the same account
 * (`FEAT-USR-001` `RN-1`). If the value object accepted both spellings, the
 * unique index would not stop the duplicate.
 */
final readonly class Email implements \Stringable
{
    private const MAX_LENGTH = 254;

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $normalised = strtolower(trim($value));

        if ('' === $normalised) {
            throw InvalidValue::because('The email address cannot be empty.');
        }

        if (\strlen($normalised) > self::MAX_LENGTH) {
            throw InvalidValue::because('The email address is too long.');
        }

        if (false === filter_var($normalised, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidValue::because('The email address is not valid.');
        }

        return new self($normalised);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * The part before the `@`, which is where the first username comes from
     * (`FEAT-USR-033`).
     */
    public function localPart(): string
    {
        return substr($this->value, 0, (int) strpos($this->value, '@'));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
