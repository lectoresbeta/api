<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\WebAddress;
use LectoresBeta\User\AuthorPage\Domain\Exception\PublishedBookRefused;

/**
 * El enlace «Comprar» de una obra publicada (`FEAT-USR-029` `RN-5`).
 *
 * **Sale de la plataforma**, y por eso se valida aquí en vez de confiar en
 * que el cliente lo pinte con cuidado: lo escribe un usuario y lo pulsa
 * cualquiera que abra su perfil.
 *
 * Solo `http` y `https`, y el servidor **no la visita**. Las dos reglas están
 * en `WebAddress`, compartidas con las referencias de la página de autor
 * (`FEAT-USR-015`): son de seguridad, y una regla de seguridad escrita dos
 * veces acaba teniendo una copia vieja.
 */
final readonly class PurchaseLink implements \Stringable
{
    /** Lo que cabe en la columna. Una dirección de compra real es mucho más corta. */
    public const MAX_LENGTH = 512;

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

        // La regla vive en `WebAddress` porque se aplica también a las
        // referencias de la página de autor (`FEAT-USR-015`), y es de
        // seguridad: escrita dos veces, una de las dos copias se queda sin el
        // día que alguien toque los esquemas permitidos.
        if (!WebAddress::isSafe($trimmed)) {
            throw PublishedBookRefused::invalidPurchaseUrl();
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
