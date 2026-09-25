<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese criterio de orden no se puede servir (`FEAT-COM-006`).
 *
 * **No se ignora en silencio**, por lo mismo que en el resto del proyecto: un
 * orden desconocido que devuelve la lista en cualquier orden parece funcionar,
 * y el error solo se descubre cuando alguien se fía de un orden que nunca se
 * aplicó.
 *
 * Hoy cae aquí «más relevantes», que es el que enseña el desplegable por
 * defecto. La fórmula está decidida —`apoyos + 2 × respuestas`— y los apoyos
 * no existen (`FEAT-COM-030`): servir media fórmula y llamarla «relevancia»
 * sería ordenar por algo que no es lo que dice el nombre.
 */
final class UnsupportedCommentSort extends \DomainException implements BusinessFailure, FailureDetails
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
        return new self($supported, \sprintf('There is no comment sort called %s.', $sort));
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
