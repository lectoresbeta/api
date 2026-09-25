<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\WebAddress;
use LectoresBeta\User\AuthorPage\Domain\Exception\AuthorLinkRefused;

/**
 * Una referencia de la página de autor: su web, su cuenta en otra red, su
 * blog (`FEAT-USR-015`).
 *
 * Misma regla que el enlace de compra y por el mismo motivo: **la escribe un
 * usuario y la pulsa cualquiera que abra su perfil**. Vive en `WebAddress`
 * para que solo haya una.
 */
final readonly class AuthorLinkUrl implements \Stringable
{
    /** Lo que cabe en la columna. Una dirección real es mucho más corta. */
    public const MAX_LENGTH = 512;

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

        if ('' === $trimmed || mb_strlen($trimmed) > self::MAX_LENGTH || !WebAddress::isSafe($trimmed)) {
            throw AuthorLinkRefused::invalidUrl();
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
