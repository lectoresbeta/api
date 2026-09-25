<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese fichero no se ha podido leer (`FEAT-WRK-002`).
 *
 * Los tres casos se distinguen **a propósito**, al revés que casi todo en
 * esta API: aquí quien pregunta es el dueño del fichero, no hay nada que
 * proteger, y lo único que le sirve es saber qué arreglar. «No se ha podido
 * procesar» a secas manda a alguien a probar cinco veces lo mismo.
 */
final class DocumentNotReadable extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<string> $accepted
     */
    public static function inThatFormat(array $accepted): self
    {
        return new self(
            'UNSUPPORTED_FILE_TYPE',
            FailureKind::INVALID,
            \sprintf('Upload one of: %s.', implode(', ', $accepted)),
        );
    }

    /**
     * El fichero dice ser una cosa y es otra.
     *
     * Se comprueba por **contenido y no por extensión** (`file-uploads.md`):
     * renombrar algo a `.docx` es lo primero que prueba quien quiere colar
     * otra cosa.
     */
    public static function becauseItIsCorrupt(): self
    {
        return new self(
            'FILE_NOT_READABLE',
            FailureKind::INVALID,
            'That file could not be opened. It may be damaged, or not be what its name says.',
        );
    }

    public static function withoutAnyText(): self
    {
        return new self(
            'FILE_HAS_NO_TEXT',
            FailureKind::INVALID,
            'There is no text in that file.',
        );
    }

    public static function becauseItIsTooLarge(int $maxBytes): self
    {
        return new self(
            'FILE_TOO_LARGE',
            FailureKind::TOO_LARGE,
            \sprintf('That file is over %d MB.', intdiv($maxBytes, 1024 * 1024)),
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return $this->failureKind;
    }
}
