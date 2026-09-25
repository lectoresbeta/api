<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede publicar eso (`FEAT-COM-002`).
 *
 * Fíjate en qué **no** está aquí: la publicación de otra persona no da un
 * error de permiso, da `PostNotFound`. Un `403` sobre una publicación que no
 * puedes ver confirmaría que existe, y eso ya es información sobre quien la
 * escribió.
 */
final class PostRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Ni texto ni adjunto.
     *
     * Es la única forma de estar vacía: una foto sin texto es una publicación
     * legítima, y obligar a escribir algo junto a ella solo consigue que la
     * gente escriba un punto.
     */
    public static function empty(): self
    {
        return new self(
            'EMPTY_POST',
            FailureKind::INVALID,
            'A post needs text or an attachment.',
        );
    }

    /**
     * Más de un adjunto.
     *
     * Se rechaza en vez de quedarse con el primero: quien manda dos cree que
     * va a publicar dos, y elegir por él le enseñaría el resultado cuando ya
     * no puede cambiarlo.
     */
    public static function tooManyAttachments(): self
    {
        return new self(
            'TOO_MANY_ATTACHMENTS',
            FailureKind::INVALID,
            'A post carries one attachment at most.',
        );
    }

    public static function imageTooLarge(): self
    {
        return new self(
            'FILE_TOO_LARGE',
            FailureKind::TOO_LARGE,
            'That image is too large.',
        );
    }

    public static function unsupportedImage(): self
    {
        return new self(
            'UNSUPPORTED_FILE_TYPE',
            FailureKind::INVALID,
            'That file is not an image this platform can read.',
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
