<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * El fondo no se puede guardar, y esto es por qué (`FEAT-USR-016`).
 *
 * Los cuatro casos se distinguen a propósito, igual que en `AvatarRefused`:
 * aquí no hay nada que proteger y quien sube una imagen necesita saber qué
 * hacer con ella. «Pesa demasiado», «no es una imagen» y «está corrupta»
 * llevan a tres acciones distintas.
 */
final class CoverRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missing(): self
    {
        return new self('COVER_REQUIRED', FailureKind::INVALID, 'No image was sent.');
    }

    public static function tooLarge(): self
    {
        return new self('COVER_TOO_LARGE', FailureKind::TOO_LARGE, 'The image is larger than 4 MB.');
    }

    /**
     * El tipo se decide **por el contenido**, no por la extensión ni por el
     * `Content-Type`: los dos los escribe quien sube el fichero.
     */
    public static function unsupportedType(): self
    {
        return new self(
            'UNSUPPORTED_COVER_TYPE',
            FailureKind::INVALID,
            'That file type is not accepted. Choose a JPEG, PNG or WebP image.',
        );
    }

    public static function unreadable(): self
    {
        return new self('UNREADABLE_COVER', FailureKind::INVALID, 'That image could not be read.');
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
