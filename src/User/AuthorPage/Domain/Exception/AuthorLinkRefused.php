<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Esa referencia no entra en la página de autor (`FEAT-USR-015`).
 */
final class AuthorLinkRefused extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function invalidUrl(): self
    {
        return new self(
            'INVALID_AUTHOR_LINK',
            'A reference must be an http or https address.',
        );
    }

    public static function withoutALabel(): self
    {
        return new self(
            'AUTHOR_LINK_WITHOUT_LABEL',
            'A reference needs a label: a bare address says nothing about where it goes.',
        );
    }

    /**
     * Un perfil con veinte enlaces deja de ser una página de autor y pasa a
     * ser un directorio de enlaces, que es otra cosa y atrae a quien quiere
     * justamente eso.
     */
    public static function tooMany(int $limit): self
    {
        return new self(
            'TOO_MANY_AUTHOR_LINKS',
            \sprintf('An author page carries %d references at most.', $limit),
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
