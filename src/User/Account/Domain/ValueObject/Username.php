<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * The public `@handle` (`decision:0005`).
 *
 * Unique across the platform, but **not identity**: it can be changed, and a
 * freed name is reserved for 30 days as an alias before anyone else can take
 * it. Identity is `UserId`.
 */
final readonly class Username implements \Stringable
{
    public const MIN_LENGTH = 3;
    public const MAX_LENGTH = 30;

    private const PATTERN = '/^[a-z0-9_]+$/';

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

        if (mb_strlen($normalised) < self::MIN_LENGTH || mb_strlen($normalised) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('The username must be between %d and %d characters.', self::MIN_LENGTH, self::MAX_LENGTH));
        }

        if (1 !== preg_match(self::PATTERN, $normalised)) {
            throw InvalidValue::because('The username may only contain a-z, 0-9 and underscores.');
        }

        return new self($normalised);
    }

    /**
     * Turns the local part of an email into a candidate username
     * (`FEAT-USR-033`). It can still collide: making it unique is the
     * application's job, because it has to look in two tables.
     */
    public static function candidateFrom(string $source, int $suffix = 0): self
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9_]/', '', strtolower($source)));
        $base = '' === $base ? 'usuario' : $base;

        $tail = $suffix > 0 ? '_'.$suffix : '';
        $base = mb_substr($base, 0, self::MAX_LENGTH - mb_strlen($tail));

        while (mb_strlen($base.$tail) < self::MIN_LENGTH) {
            $base .= '0';
        }

        return self::fromString($base.$tail);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
