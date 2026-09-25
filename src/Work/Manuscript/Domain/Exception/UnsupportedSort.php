<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * El criterio de orden no existe (`FEAT-WRK-015`).
 *
 * **No se ignora en silencio.** Un `sort` desconocido que devuelve la lista
 * en cualquier orden parece funcionar, y el error solo se descubre cuando
 * alguien se fía de un orden que nunca se aplicó.
 *
 * Lleva los admitidos para que el cliente pueda corregirse sin ir a la
 * documentación.
 */
final class UnsupportedSort extends \DomainException implements BusinessFailure, FailureDetails
{
    /**
     * @param list<string> $supported
     */
    private function __construct(private readonly array $supported, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param list<string> $supported
     */
    public static function of(string $sort, array $supported): self
    {
        return new self($supported, \sprintf('There is no sort called %s.', $sort));
    }

    public function failureDetails(): array
    {
        return ['supportedSorts' => implode(',', $this->supported)];
    }

    public function errorCode(): string
    {
        return 'UNSUPPORTED_SORT';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
