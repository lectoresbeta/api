<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject;

use LectoresBeta\Reading\BetaReaderGroup\Domain\Exception\GroupRefused;

/**
 * Cómo el autor llama a una de sus listas (`FEAT-RDG-007` `RN-2`, `RN-3`).
 *
 * Se recorta y no puede quedar vacío. La comparación es **sin mayúsculas**
 * porque dos grupos que solo se distinguen por una mayúscula son dos entradas
 * indistinguibles en un desplegable.
 */
final readonly class BetaReaderGroupName implements \Stringable
{
    public const MAX = 80;

    private function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ('' === $trimmed) {
            throw GroupRefused::nameIsRequired();
        }

        if (mb_strlen($trimmed) > self::MAX) {
            throw GroupRefused::nameIsTooLong();
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * La clave con la que se comprueba `RN-3`. Vive aquí y no en el
     * repositorio para que la regla sea una y no dos.
     */
    public function comparable(): string
    {
        return mb_strtolower($this->value);
    }
}
