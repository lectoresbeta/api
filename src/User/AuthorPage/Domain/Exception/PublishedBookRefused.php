<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\User\AuthorPage\Domain\Service\PublishedBookPolicy;

/**
 * La obra publicada no se puede guardar, y esto es por qué (`FEAT-USR-029`).
 *
 * `notYours()` responde `403` y **no `404`**, al revés que en casi todo lo
 * demás. Esconder la existencia de algo solo tiene sentido cuando lo que se
 * protege es saber que existe, y una obra publicada se enseña en un perfil
 * abierto: fingir que no está sería mentirle a quien la acaba de ver, sin
 * ocultarle nada.
 */
final class PublishedBookRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self(
            'PUBLISHED_BOOK_NOT_FOUND',
            FailureKind::NOT_FOUND,
            'That published book does not exist.',
        );
    }

    public static function notYours(): self
    {
        return new self(
            'NOT_YOUR_PUBLISHED_BOOK',
            FailureKind::FORBIDDEN,
            'Only its author manages a published book.',
        );
    }

    public static function missingTitle(): self
    {
        return new self(
            'VALIDATION_FAILED',
            FailureKind::INVALID,
            'A published book needs a title.',
        );
    }

    public static function titleTooLong(): self
    {
        return new self(
            'VALIDATION_FAILED',
            FailureKind::INVALID,
            \sprintf('The title is longer than %d characters.', PublishedBookPolicy::TITLE_MAX_LENGTH),
        );
    }

    /**
     * **La editorial no se valida contra ningún catálogo** (`RN-9`): lo único
     * que se le exige es caber. Validarla dejaría fuera justo al perfil más
     * habitual de esta plataforma, el que se publica por su cuenta y responde
     * «Amazon».
     */
    public static function publisherTooLong(): self
    {
        return new self(
            'VALIDATION_FAILED',
            FailureKind::INVALID,
            \sprintf('The publisher is longer than %d characters.', PublishedBookPolicy::PUBLISHER_MAX_LENGTH),
        );
    }

    public static function implausibleYear(): self
    {
        return new self(
            'VALIDATION_FAILED',
            FailureKind::INVALID,
            'That publication year is not a plausible one.',
        );
    }

    public static function invalidPurchaseUrl(): self
    {
        return new self(
            'INVALID_PURCHASE_URL',
            FailureKind::INVALID,
            'The purchase link must be an absolute http or https address.',
        );
    }

    public static function missingCover(): self
    {
        return new self(
            'VALIDATION_FAILED',
            FailureKind::INVALID,
            'No cover image was sent.',
        );
    }

    /**
     * Los tres fallos de una portada se distinguen a propósito: «pesa
     * demasiado», «no es una imagen» y «está corrupta» llevan a tres cosas
     * distintas, y aquí no hay nada que proteger callándose cuál fue.
     */
    public static function coverTooLarge(): self
    {
        return new self(
            'FILE_TOO_LARGE',
            FailureKind::TOO_LARGE,
            'The cover is larger than 2 MB.',
        );
    }

    /**
     * El tipo se decide **por el contenido**, no por la extensión ni por el
     * `Content-Type`: los dos los escribe quien sube el fichero.
     */
    public static function unsupportedCoverType(): self
    {
        return new self(
            'UNSUPPORTED_FILE_TYPE',
            FailureKind::INVALID,
            'That file type is not accepted. Choose a JPEG, PNG or WebP image.',
        );
    }

    public static function unreadableCover(): self
    {
        return new self(
            'INVALID_IMAGE',
            FailureKind::INVALID,
            'That image could not be read.',
        );
    }

    public static function tooMany(): self
    {
        return new self(
            'PUBLISHED_BOOK_LIMIT_REACHED',
            FailureKind::CONFLICT,
            \sprintf('A profile holds at most %d published books.', PublishedBookPolicy::MAX_PER_AUTHOR),
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
