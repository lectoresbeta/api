<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No hay condiciones vigentes que aceptar (`FEAT-USR-024`).
 *
 * **Falla cerrado, y es deliberado.** Si no hay ningún texto publicado no hay
 * nada a lo que consentir, y dar de alta a alguien guardando una versión que
 * no corresponde a ningún documento sería dejar constancia de un
 * consentimiento vacío. Eso es peor que no dar de alta: parece una prueba y
 * no lo es.
 *
 * En la práctica no debería verse nunca — las versiones vigentes las siembra
 * una migración— y si se ve, es una avería de operaciones, no del usuario.
 */
final class NoLegalDocumentsPublished extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('There are no legal documents in force to accept right now.');
    }

    public function errorCode(): string
    {
        return 'NO_LEGAL_DOCUMENTS_PUBLISHED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
