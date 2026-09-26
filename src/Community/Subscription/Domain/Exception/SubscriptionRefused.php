<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede seguir a esa persona, y esto es por qué (`FEAT-COM-010`).
 *
 * **Las dos razones son las dos únicas que hay**, y conviene que estén
 * juntas: lo que no está aquí no es un error. Seguir a quien ya se sigue no
 * lo es —el estado que se pedía ya se cumple— y dejar de seguir a quien no se
 * sigue, tampoco.
 */
final class SubscriptionRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * `404` y no `422`: quien pide seguir un identificador que no existe está
     * preguntando por una persona que no está, y esa es la respuesta.
     */
    public static function authorNotFound(): self
    {
        return new self(
            'USER_NOT_FOUND',
            FailureKind::NOT_FOUND,
            'That account does not exist.',
        );
    }

    public static function yourself(): self
    {
        return new self(
            'CANNOT_SUBSCRIBE_TO_YOURSELF',
            FailureKind::INVALID,
            'You cannot follow yourself.',
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
