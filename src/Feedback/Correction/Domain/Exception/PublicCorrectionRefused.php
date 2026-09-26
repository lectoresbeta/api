<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede corregir eso por ese enlace (`FEAT-FBK-008`).
 */
final class PublicCorrectionRefused extends \DomainException implements BusinessFailure, FailureDetails
{
    /**
     * @param array<string, scalar> $details
     */
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        private readonly array $details,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unknownLink(): self
    {
        return new self('PUBLIC_LINK_NOT_FOUND', FailureKind::NOT_FOUND, [], 'That link does not open anything.');
    }

    /**
     * Revocado, caducado o de una obra bloqueada: `410` y no `404`. Quien lo
     * tiene sabe que existió.
     */
    public static function linkGone(): self
    {
        return new self('PUBLIC_LINK_GONE', FailureKind::GONE, [], 'That link no longer opens anything.');
    }

    /**
     * `RN-1`, la fuga que hay que tapar. Sin esto, el autor podría pegar el
     * enlace en su muro y conseguir que usuarios registrados le corrigiesen
     * gratis: él se ahorraría los créditos y ellos perderían los suyos.
     */
    public static function forSignedInReader(string $chapterId): self
    {
        return new self(
            'USE_THE_NORMAL_FLOW',
            FailureKind::CONFLICT,
            ['chapterId' => $chapterId],
            'A signed-in reader corrects through the normal flow, and gets paid for it.',
        );
    }

    /**
     * `RN-4`: diez por defecto. Se puede seguir leyendo; lo que se agota es
     * la posibilidad de corregir.
     */
    public static function linkIsFull(int $maxCorrections): self
    {
        return new self(
            'PUBLIC_LINK_FULL',
            FailureKind::CONFLICT,
            ['maxCorrections' => $maxCorrections],
            'That link has taken all the corrections it admits.',
        );
    }

    public static function chapterNotInThatWork(): self
    {
        return new self('CHAPTER_NOT_FOUND', FailureKind::NOT_FOUND, [], 'That chapter is not behind this link.');
    }

    public static function thereIsNoQuestionnaire(): self
    {
        return new self('NO_QUESTIONNAIRE', FailureKind::CONFLICT, [], 'That chapter has nothing to answer.');
    }

    /**
     * `RN-7`: quien envía está aportando un texto propio sin haber aceptado
     * nada. La casilla la comprueba el servidor, no solo la pantalla.
     */
    public static function withoutAcceptingTerms(): self
    {
        return new self('TERMS_NOT_ACCEPTED', FailureKind::INVALID, [], 'The terms have to be accepted before sending.');
    }

    public function failureDetails(): array
    {
        return $this->details;
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
