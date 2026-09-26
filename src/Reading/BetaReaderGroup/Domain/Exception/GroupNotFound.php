<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No está, o no se puede reconocer que está (`FEAT-RDG-007`).
 *
 * El grupo de otra persona responde lo mismo que uno inexistente. Un grupo es
 * una anotación privada del autor sobre otras personas; confirmar que existe
 * ya diría algo de quién tiene apuntado a quién.
 */
final class GroupNotFound extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function group(): self
    {
        return new self('GROUP_NOT_FOUND', 'That beta reader group does not exist.');
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
