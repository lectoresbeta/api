<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Rating\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Esa obra no se puede valorar, al menos no por quien pregunta
 * (`FEAT-FBK-002`).
 *
 * `withoutHavingCorrectedIt()` **sí explica por qué**, y puede: para llegar
 * hasta ahí hay que poder ver la obra, así que no revela nada que quien
 * pregunta no tuviera delante. Y explicarlo es lo correcto — «corrige un
 * capítulo y podrás valorarla» es una instrucción accionable, no un muro.
 *
 * `create()` es el `404` de siempre: una obra que no se puede ver no se
 * distingue de una que no existe.
 */
final class WorkNotRatable extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function create(): self
    {
        return new self('WORK_NOT_FOUND', FailureKind::NOT_FOUND, 'That work is not there.');
    }

    public static function withoutHavingCorrectedIt(): self
    {
        return new self(
            'CORRECTION_REQUIRED_TO_RATE',
            FailureKind::FORBIDDEN,
            'Only somebody who has delivered a correction of this work can rate it.',
        );
    }

    public static function byItsAuthor(): self
    {
        return new self(
            'CANNOT_RATE_YOUR_OWN_WORK',
            FailureKind::FORBIDDEN,
            'An author does not rate their own work.',
        );
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
