<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * A password that has already been hashed.
 *
 * The plain password never becomes a value object, and never reaches this
 * layer: hashing is a port implemented in Infrastructure, and what the domain
 * stores is the result. `__toString` is deliberately absent so the hash cannot
 * end up in a log line by accident.
 */
final readonly class HashedPassword
{
    private function __construct(private string $value)
    {
    }

    public static function fromHash(string $hash): self
    {
        if ('' === trim($hash)) {
            throw InvalidValue::because('The password hash cannot be empty.');
        }

        return new self($hash);
    }

    public function value(): string
    {
        return $this->value;
    }
}
