<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\ValueObject;

use LectoresBeta\User\AuthorPage\Domain\Exception\AuthorLinkRefused;

/**
 * Lo que se lee en el botón de una referencia (`FEAT-USR-015`).
 *
 * **Sesenta caracteres, en una sola línea y sin marcado.** No es el mismo
 * caso que una biografía, que conserva los saltos de línea porque son parte
 * de cómo se lee: esto es la cara visible de un enlace, y un salto de línea
 * dentro de un botón solo puede romper la maqueta o disfrazar el destino.
 *
 * El marcado se retira en vez de rechazarse, como en la biografía: lo que hay
 * que garantizar es que no sobreviva nada que un cliente pueda interpretar, y
 * decirle a alguien «tu texto lleva HTML» cuando ha pegado desde otro sitio
 * no ayuda a nadie.
 *
 * **Vacío se rechaza.** Una referencia sin etiqueta no se rellena con su
 * dirección: un enlace que no dice a dónde lleva es el que nadie pulsa, o el
 * que se pulsa por error, y poner la URL como texto visible invita justamente
 * a disfrazar el destino.
 */
final readonly class AuthorLinkLabel implements \Stringable
{
    public const MAX_LENGTH = 60;

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(?string $value): self
    {
        // Las etiquetas se sustituyen por un espacio y no por nada:
        // `uno<br>dos` es «uno dos» y no «unodos».
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags(str_replace('<', ' <', $value ?? ''))));

        if ('' === $plain) {
            throw AuthorLinkRefused::withoutALabel();
        }

        return new self(mb_substr($plain, 0, self::MAX_LENGTH));
    }

    public function value(): string
    {
        return $this->value;
    }
}
