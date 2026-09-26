<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\ValueObject;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * The few lines somebody writes under their photo (`FEAT-USR-008` `RN-3`,
 * `RN-4`).
 *
 * **Three hundred characters, and measured in characters on purpose.** The
 * rest of the product measures in words (`R-5`), because those limits measure
 * *effort* — what it costs to write a correction. This one measures *space*,
 * the space a text takes under a photo, and for space the right unit is the
 * character.
 *
 * **Plain text, and that is a rule and not a formatting preference.** A
 * biography is one of the few free fields a brand-new account can fill in
 * before having any reputation, which makes it the first place somebody will
 * try to put links. Markup is stripped rather than refused: what has to be
 * guaranteed is that nothing survives that a client could interpret, and
 * telling somebody «your text contains HTML» when they pasted from a word
 * processor helps nobody.
 *
 * A bare URL is still plain text and stays. Deciding that somebody's
 * biography is spam is a moderation judgement (`RN-7`), not something a
 * length check can make.
 */
final readonly class Biography implements \Stringable
{
    public const MAX_LENGTH = 300;

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * `null` for anything that says nothing: absent, empty, or reduced to
     * nothing once the markup is gone. An empty biography and no biography
     * are the same thing, and keeping both would mean two ways of rendering
     * the same nothing.
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

        // Se mide **después** de limpiar: contar el marcado contra el límite
        // castigaría a quien pega desde un procesador de textos por algo que
        // ni siquiera se va a guardar.
        if (mb_strlen($plain) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A biography cannot exceed %d characters.', self::MAX_LENGTH));
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
     * biografía de tres líneas es normal— pero se colapsan los seguidos, que
     * es como se maqueta un hueco en blanco a mano.
     */
    private static function plain(string $value): string
    {
        $withoutTags = strip_tags(str_replace('<', ' <', $value));
        $normalised = preg_replace('/[^\S\n]+/u', ' ', $withoutTags) ?? $withoutTags;
        $normalised = preg_replace('/\n{3,}/u', "\n\n", $normalised) ?? $normalised;

        return trim($normalised);
    }
}
