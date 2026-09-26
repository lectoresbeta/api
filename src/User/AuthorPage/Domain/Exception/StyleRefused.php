<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese estilo no existe (`FEAT-USR-016` `RN-2`).
 *
 * Se rechaza **nombrándolo** en lugar de caer al valor por defecto: quien
 * manda `midnight` en minúsculas o un `#112233` cree que ha cambiado algo, y
 * una página que se queda como estaba sin decir nada es un error que se busca
 * en el sitio equivocado.
 */
final class StyleRefused extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function unknownTheme(?string $value): self
    {
        return new self('UNKNOWN_AUTHOR_PAGE_THEME', \sprintf(
            '"%s" is not one of the author page themes.',
            $value ?? '',
        ));
    }

    /**
     * **Un color libre no se acepta**, ni siquiera con forma de hexadecimal.
     */
    public static function unknownAccent(?string $value): self
    {
        return new self('UNKNOWN_ACCENT_COLOUR', \sprintf(
            '"%s" is not one of the accent colours.',
            $value ?? '',
        ));
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
