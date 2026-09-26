<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Work\Manuscript\Domain\Service\GenrePolicy;

/**
 * Las temáticas declaradas no sirven (`FEAT-WRK-001`).
 *
 * Un código desconocido **se nombra**: quien está clasificando su obra
 * necesita saber cuál de los tres que envió no existe, y descartarlo en
 * silencio dejaría una obra clasificada de forma distinta a como su autor
 * cree.
 */
final class InvalidWorkGenres extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param list<string> $codes
     */
    public static function unknown(array $codes): self
    {
        return new self(
            'UNKNOWN_GENRE',
            \sprintf('These genres are not in the catalogue: %s.', implode(', ', $codes)),
        );
    }

    public static function tooMany(int $declared): self
    {
        return new self(
            'TOO_MANY_GENRES',
            \sprintf('A work declares at most %d genres; %d were sent.', GenrePolicy::MAX_GENRES, $declared),
        );
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
