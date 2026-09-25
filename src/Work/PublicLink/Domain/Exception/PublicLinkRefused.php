<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Work\PublicLink\Domain\Entity\PublicLink;

/**
 * No se puede hacer eso con ese enlace (`FEAT-WRK-010`).
 */
final class PublicLinkRefused extends \DomainException implements BusinessFailure, FailureDetails
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

    /**
     * `RN-7`: no existe y no es suya se contestan igual. Distinguirlas diría
     * que esa obra existe, que es justo lo que un enlace público protege.
     */
    public static function workNotYours(): self
    {
        return new self('WORK_NOT_FOUND', FailureKind::NOT_FOUND, [], 'That work is not one of yours.');
    }

    public static function linkNotYours(): self
    {
        return new self('PUBLIC_LINK_NOT_FOUND', FailureKind::NOT_FOUND, [], 'That link is not one of yours.');
    }

    public static function capOutOfRange(): self
    {
        return new self(
            'PUBLIC_LINK_CAP_OUT_OF_RANGE',
            FailureKind::INVALID,
            ['minimum' => 1, 'maximum' => PublicLink::MAX_CORRECTIONS_CEILING],
            \sprintf('The correction cap goes from 1 to %d.', PublicLink::MAX_CORRECTIONS_CEILING),
        );
    }

    public static function expiryInThePast(): self
    {
        return new self('PUBLIC_LINK_EXPIRY_IN_THE_PAST', FailureKind::INVALID, [], 'An expiry date has to be in the future.');
    }

    /**
     * `RN-10`: revocado, caducado o de una obra bloqueada responden `410` y
     * no `404`. Quien tiene la URL sabe que existió, y fingir lo contrario no
     * protege nada que no esté ya protegido.
     */
    public static function gone(): self
    {
        return new self('PUBLIC_LINK_GONE', FailureKind::GONE, [], 'That link no longer opens anything.');
    }

    /**
     * `FEAT-FBK-008` `RN-1`: quien tiene sesión va al flujo normal, con su
     * corrección y sus créditos. Sin esto, el autor podría pegar el enlace en
     * su muro y conseguir que le corrigieran gratis.
     */
    public static function forSignedInReader(string $workId): self
    {
        return new self(
            'USE_THE_NORMAL_FLOW',
            FailureKind::CONFLICT,
            ['workId' => $workId],
            'A signed-in reader corrects through the normal flow, and gets paid for it.',
        );
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
