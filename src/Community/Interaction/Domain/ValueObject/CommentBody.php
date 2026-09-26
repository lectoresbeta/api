<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El texto de un comentario o de una respuesta (`FEAT-COM-006` `RN-3`).
 *
 * **Texto plano**, por lo mismo que el de una publicación: acaba dentro de
 * una tarjeta junto al nombre de quien lo escribió, y nada que un cliente
 * pueda interpretar puede sobrevivir a la escritura. Los emojis se conservan;
 * en un comentario son la mitad de lo que la gente escribe.
 *
 * Mil caracteres y no cinco mil: un comentario que necesita la extensión de
 * una publicación probablemente quiere ser una publicación.
 *
 * A diferencia del cuerpo de una publicación, **aquí no puede estar vacío**.
 * Una publicación sin texto es legítima si lleva una foto; un comentario no
 * lleva nada más, así que sin texto no hay comentario.
 */
final readonly class CommentBody implements \Stringable
{
    public const MAX_LENGTH = 1000;

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(?string $value): self
    {
        $plain = self::plain($value ?? '');

        if ('' === $plain) {
            throw InvalidValue::because('A comment cannot be empty.');
        }

        // Se mide **después** de limpiar, como en todo el proyecto: contar el
        // marcado contra el límite castigaría por algo que ni se va a guardar.
        if (mb_strlen($plain) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A comment cannot exceed %d characters.', self::MAX_LENGTH));
        }

        return new self($plain);
    }

    public function value(): string
    {
        return $this->value;
    }

    private static function plain(string $value): string
    {
        $withoutTags = strip_tags(str_replace('<', ' <', $value));
        $normalised = preg_replace('/[^\S\n]+/u', ' ', $withoutTags) ?? $withoutTags;
        $normalised = preg_replace('/\n{3,}/u', "\n\n", $normalised) ?? $normalised;

        return trim($normalised);
    }
}
