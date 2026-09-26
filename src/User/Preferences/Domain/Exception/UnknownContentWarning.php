<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Se ha pedido excluir una etiqueta que no existe (`FEAT-USR-043`).
 *
 * Se rechaza en vez de guardarse porque una exclusión que no coincide con
 * ninguna etiqueta real **no filtra nada y parece que sí**, que es la peor
 * manera de fallar en algo que la gente configura precisamente para no
 * llevarse un disgusto.
 */
final class UnknownContentWarning extends \DomainException implements BusinessFailure, FailureDetails
{
    /**
     * @param list<string> $codes
     */
    private function __construct(private readonly array $codes, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param list<string> $codes
     */
    public static function of(array $codes): self
    {
        return new self($codes, \sprintf('These content warnings are not in the catalogue: %s.', implode(', ', $codes)));
    }

    public function failureDetails(): array
    {
        return ['unknownWarnings' => implode(',', $this->codes)];
    }

    public function errorCode(): string
    {
        return 'UNKNOWN_CONTENT_WARNING';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
