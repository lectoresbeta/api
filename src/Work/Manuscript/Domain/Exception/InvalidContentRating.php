<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La clasificación declarada no sirve (`FEAT-WRK-017`).
 *
 * Las dos formas de no servir se distinguen porque llevan a arreglos
 * distintos: una etiqueta que no existe es un error de quien llama, y no
 * decir para qué público es la obra es una declaración incompleta.
 *
 * Las etiquetas desconocidas **se nombran**. Descartarlas en silencio dejaría
 * una obra etiquetada de forma distinta a como su autor cree, y el autor
 * responde de eso: etiquetar mal es reclamable (`RN-7`).
 */
final class InvalidContentRating extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param list<string> $warnings
     */
    public static function unknownWarnings(array $warnings): self
    {
        return new self(
            'UNKNOWN_CONTENT_WARNING',
            \sprintf('These content warnings do not exist: %s.', implode(', ', $warnings)),
        );
    }

    public static function undeclaredAudience(): self
    {
        return new self(
            'AUDIENCE_NOT_DECLARED',
            'A content rating states whether the work is adults-only; the flag is required.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
