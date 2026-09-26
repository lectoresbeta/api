<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No existe, o no es tuya (`FEAT-COM-012` `RN-1`).
 *
 * **Las dos cosas responden igual, y es la regla.** Un `403` a una
 * conversación ajena confirmaría que existe, y con ella que esas dos personas
 * hablan. Quién habla con quién no es información que se le deba a nadie.
 */
final class ConversationNotFound extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That conversation is not there.');
    }

    public function errorCode(): string
    {
        return 'CONVERSATION_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
