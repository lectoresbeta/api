<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La foto no se puede guardar, y esto es por qué (`FEAT-USR-037`).
 *
 * Los tres casos se distinguen **a propósito**, al revés que en otros sitios
 * donde dos errores comparten código para no contar de más: aquí no hay nada
 * que proteger y quien sube una foto necesita saber qué hacer con ella.
 * «Pesa demasiado», «no es una imagen» y «está corrupta» llevan a tres
 * acciones distintas.
 */
final class AvatarRefused extends \DomainException implements BusinessFailure
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
        return new self(
            'VALIDATION_FAILED',
            FailureKind::INVALID,
            'No image was sent.',
        );
    }

    /**
     * El tipo se decide **por el contenido**, no por la extensión ni por el
     * `Content-Type`: los dos los escribe quien sube el fichero.
     */
    public static function unsupportedType(): self
    {
        return new self(
            'UNSUPPORTED_FILE_TYPE',
            FailureKind::INVALID,
            'That file type is not accepted. Choose a JPEG, PNG or WebP image.',
        );
    }

    public static function tooLarge(): self
    {
        return new self(
            'FILE_TOO_LARGE',
            FailureKind::TOO_LARGE,
            'The image is larger than 2 MB.',
        );
    }

    public static function unreadable(): self
    {
        return new self(
            'INVALID_IMAGE',
            FailureKind::INVALID,
            'That image could not be read.',
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
