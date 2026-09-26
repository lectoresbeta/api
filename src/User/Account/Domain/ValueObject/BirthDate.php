<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Date of birth. Private data: it never appears in the public API
 * (`FEAT-USR-022`).
 *
 * It is collected because the age gate for `ADULTS_ONLY` works off it
 * (`FEAT-USR-043` `RN-4`), not to decorate the profile.
 */
final readonly class BirthDate
{
    private function __construct(private \DateTimeImmutable $value)
    {
    }

    public static function fromDate(\DateTimeImmutable $value, \DateTimeImmutable $now): self
    {
        $date = $value->setTime(0, 0);

        if ($date >= $now->setTime(0, 0)) {
            throw InvalidValue::because('The date of birth must be in the past.');
        }

        return new self($date);
    }

    public function value(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function ageAt(\DateTimeImmutable $moment): int
    {
        return (int) $this->value->diff($moment)->format('%y');
    }
}
