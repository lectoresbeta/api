<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El texto de una publicación (`FEAT-COM-002` `RN-4`, `RN-11`).
 *
 * **Texto plano, y es una regla y no una preferencia de formato.** El muro lo
 * pinta cualquier cliente, y lo que se guarda aquí acaba dentro de una tarjeta
 * junto al nombre de quien lo escribió: nada que un cliente pueda interpretar
 * puede sobrevivir a la escritura. El marcado se quita en vez de rechazarse,
 * porque quien pega desde un procesador de textos no ha hecho nada malo y
 * decirle «tu texto contiene HTML» no le ayuda a entenderlo.
 *
 * Los **emojis se conservan**: son texto, no marcado, y en un muro son la
 * mitad de lo que la gente escribe.
 *
 * Cinco mil caracteres, medidos **después** de limpiar. Contar el marcado
 * contra el límite castigaría por algo que ni siquiera se va a guardar.
 *
 * Una URL escrita a mano sigue siendo texto plano y se queda. Decidir que una
 * publicación es spam es un juicio de moderación, no algo que pueda hacer una
 * comprobación de longitud.
 */
final readonly class PostBody implements \Stringable
{
    public const MAX_LENGTH = 5000;

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * `null` para todo lo que no dice nada: ausente, vacío, o reducido a nada
     * al quitarle el marcado. Una publicación sin texto es legítima si lleva
     * adjunto, así que quien llama decide qué hacer con el `null`; lo que no
     * existe es la publicación vacía.
     */
    public static function fromString(?string $value): ?self
    {
        if (null === $value) {
            return null;
        }

        $plain = self::plain($value);

        if ('' === $plain) {
            return null;
        }

        if (mb_strlen($plain) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A post cannot exceed %d characters.', self::MAX_LENGTH));
        }

        return new self($plain);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Sin etiquetas y sin espacios de sobra.
     *
     * Las etiquetas se sustituyen por un espacio y no por nada: `uno<br>dos`
     * es «uno dos» y no «unodos». Los saltos de línea se conservan —una
     * publicación de varios párrafos es normal— y se colapsan los seguidos,
     * que es como se maqueta un hueco en blanco a mano.
     *
     * Es la misma limpieza que hace `Biography`, y está escrita dos veces a
     * propósito: son contextos distintos con límites distintos, y compartirla
     * ataría el muro a lo que decida el perfil.
     */
    private static function plain(string $value): string
    {
        $withoutTags = strip_tags(str_replace('<', ' <', $value));
        $normalised = preg_replace('/[^\S\n]+/u', ' ', $withoutTags) ?? $withoutTags;
        $normalised = preg_replace('/\n{3,}/u', "\n\n", $normalised) ?? $normalised;

        return trim($normalised);
    }
}
