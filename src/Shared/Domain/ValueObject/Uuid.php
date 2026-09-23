<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Base of every identifier in the system.
 *
 * UUID v7 and not v4: the first 48 bits are a millisecond timestamp, so the
 * values sort by creation time. That matters for a PostgreSQL primary key —
 * random identifiers scatter writes across the whole B-tree — and it gives
 * every listing a stable tie-break without an extra column.
 *
 * Generation is written by hand rather than taken from a library because this
 * class lives in Domain, and Domain depends on nothing outside PHP itself.
 */
abstract readonly class Uuid implements \Stringable
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    final private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * A new identifier for something that is being created right now.
     */
    public static function generate(): static
    {
        $milliseconds = (int) (microtime(true) * 1000);

        $bytes = substr(pack('J', $milliseconds), 2, 6).random_bytes(10);

        // Version 7 in the high nibble of byte 6, RFC 9562 variant in byte 8.
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x70);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        return new static(self::format($bytes));
    }

    /**
     * An identifier that already exists: it comes from the database, from a
     * request or from an integration event.
     */
    public static function fromString(string $value): static
    {
        $normalised = strtolower(trim($value));

        if (1 !== preg_match(self::PATTERN, $normalised)) {
            throw InvalidValue::because(\sprintf('"%s" is not a valid identifier.', $value));
        }

        return new static($normalised);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value === $other->value;
    }

    private static function format(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'
            .substr($hex, 8, 4).'-'
            .substr($hex, 12, 4).'-'
            .substr($hex, 16, 4).'-'
            .substr($hex, 20, 12);
    }
}
