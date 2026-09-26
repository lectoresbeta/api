<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Se ha aceptado una versión que ya no es la vigente (`FEAT-USR-024`).
 *
 * **Decidido aquí**, que la ficha lo dejaba por definir: se rechaza y se pide
 * releer. Aceptar una versión antigua dejaría constancia de un consentimiento
 * sobre un texto que ya no es el que rige, y ese registro no demuestra nada —
 * que es justo lo contrario de para lo que existe.
 *
 * Lleva **qué versión es la vigente** para que el formulario pueda recargar
 * el texto y volver a pedirlo, en vez de dejar a alguien atascado sin saber
 * qué ha pasado.
 */
final class LegalVersionOutdated extends \DomainException implements BusinessFailure, FailureDetails
{
    /**
     * @param array<string, string> $inForce
     */
    private function __construct(private readonly array $inForce)
    {
        parent::__construct('The legal documents have changed. Read them again before accepting.');
    }

    /**
     * @param array<string, string> $inForce versión vigente por tipo de documento
     */
    public static function insteadOf(array $inForce): self
    {
        return new self($inForce);
    }

    public function failureDetails(): array
    {
        return $this->inForce;
    }

    public function errorCode(): string
    {
        return 'LEGAL_VERSION_OUTDATED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
