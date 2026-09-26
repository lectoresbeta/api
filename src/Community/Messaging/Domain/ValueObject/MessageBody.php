<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\ValueObject;

use LectoresBeta\Community\Messaging\Domain\Exception\MessageRefused;

/**
 * El texto de un mensaje directo (`FEAT-COM-011` `RN-6`).
 *
 * **Texto plano, sin marcado.** Una publicación admite formato porque es algo
 * que alguien compone; un mensaje es una conversación, y aceptar HTML aquí
 * abriría una superficie de saneado para nada.
 *
 * Cuatro mil caracteres, que no es un límite literario sino el punto en el
 * que un mensaje deja de ser un mensaje. Quien necesite más está escribiendo
 * otra cosa.
 */
final readonly class MessageBody implements \Stringable
{
    public const MAX_LENGTH = 4000;

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(?string $value): self
    {
        $trimmed = trim((string) $value);

        if ('' === $trimmed) {
            throw MessageRefused::empty();
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw MessageRefused::tooLong();
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
