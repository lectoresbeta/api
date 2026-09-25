<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El enlace externo de una publicación (`FEAT-COM-002` `RN-6`).
 *
 * **Se guarda la dirección y nada más** (`C-8`). El servidor no la visita:
 * hacerlo convertiría cada publicación en una petición saliente hacia donde
 * diga quien publica, y eso es una capacidad que hay que decidir aparte, no
 * un efecto secundario de tener una tarjeta más bonita.
 *
 * Solo `http` y `https`. Cualquier otro esquema —`javascript:`, `data:`,
 * `file:`— es una forma de que el cliente ejecute algo o lea algo al pulsar,
 * y lo que aquí parece un enlace allí es un botón que alguien ajeno escribió.
 */
final readonly class PostLink implements \Stringable
{
    public const MAX_LENGTH = 2048;

    private const ALLOWED_SCHEMES = ['http', 'https'];

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(?string $value): ?self
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidValue::because('The link is too long.');
        }

        $scheme = parse_url($trimmed, \PHP_URL_SCHEME);

        if (!\is_string($scheme) || !\in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true)) {
            throw InvalidValue::because('A link must start with http:// or https://.');
        }

        // `parse_url` acepta cosas que no son direcciones alcanzables, como
        // `http://`, así que el filtro va después del esquema y no en su
        // lugar: cada uno atrapa lo que el otro deja pasar.
        if (false === filter_var($trimmed, \FILTER_VALIDATE_URL)) {
            throw InvalidValue::because('That is not a valid link.');
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
