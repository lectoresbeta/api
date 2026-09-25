<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\ValueObject;

use LectoresBeta\User\AuthorPage\Domain\Exception\PublishedBookRefused;

/**
 * El enlace «Comprar» de una obra publicada (`FEAT-USR-029` `RN-5`).
 *
 * **Sale de la plataforma**, y por eso se valida aquí en vez de confiar en
 * que el cliente lo pinte con cuidado: lo escribe un usuario y lo pulsa
 * cualquiera que abra su perfil.
 *
 * Solo `http` y `https`. Cualquier otro esquema —`javascript:`, `data:`,
 * `file:`— convierte lo que parece un enlace en un botón que alguien ajeno
 * escribió.
 *
 * El servidor **no lo visita**: comprobar que la página existe convertiría
 * cada guardado en una petición saliente hacia donde diga quien la escribe.
 */
final readonly class PurchaseLink implements \Stringable
{
    /** Lo que cabe en la columna. Una dirección de compra real es mucho más corta. */
    public const MAX_LENGTH = 512;

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
            throw PublishedBookRefused::invalidPurchaseUrl();
        }

        $scheme = parse_url($trimmed, \PHP_URL_SCHEME);

        if (!\is_string($scheme) || !\in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true)) {
            throw PublishedBookRefused::invalidPurchaseUrl();
        }

        // `parse_url` acepta cosas que no son direcciones alcanzables, como
        // `http://`, así que el filtro va después del esquema y no en su
        // lugar: cada uno atrapa lo que el otro deja pasar.
        if (false === filter_var($trimmed, \FILTER_VALIDATE_URL)) {
            throw PublishedBookRefused::invalidPurchaseUrl();
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
