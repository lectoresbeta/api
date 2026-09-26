<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede resolver esa reclamación así (`FEAT-MOD-002`).
 *
 * Quien llama es un moderador, no un usuario cualquiera, y eso cambia el
 * criterio habitual de la plataforma: aquí **sí se distinguen los motivos**,
 * porque quien está delante tiene derecho a saber por qué no puede actuar y
 * no está averiguando nada que no pueda ver ya.
 *
 * Con una excepción, y es la importante: **ser parte responde `403`, no una
 * explicación**. `RN-1` se cumple antes en la cola —esas reclamaciones ni
 * siquiera se le muestran— y este error es solo la segunda puerta, la de
 * quien llega por la URL directa.
 */
final class ClaimReviewRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $errorCode,
        private readonly FailureKind $kind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function becauseItDoesNotExist(): self
    {
        return new self(
            'CLAIM_NOT_FOUND',
            FailureKind::NOT_FOUND,
            'There is no such claim.',
        );
    }

    /**
     * `RN-1`. Nadie modera un asunto en el que es parte: ni una reclamación
     * que presentó, ni una que le señala.
     */
    public static function becauseTheModeratorIsAParty(): self
    {
        return new self(
            'CLAIM_MODERATOR_IS_PARTY',
            FailureKind::FORBIDDEN,
            'Nobody reviews a matter they are part of.',
        );
    }

    /**
     * `RN-3`: el estado no retrocede. Un error se corrige con una acción
     * administrativa nueva y motivada, no reabriendo el expediente.
     */
    public static function becauseItIsAlreadyResolved(string $status): self
    {
        return new self(
            'CLAIM_ALREADY_RESOLVED',
            FailureKind::CONFLICT,
            \sprintf('That claim is already %s and does not go back.', strtolower($status)),
        );
    }

    /**
     * `RN-2`. Un expediente sin motivo no se puede auditar y no se puede
     * defender si alguien discute la decisión — que, con créditos y sanciones
     * de por medio, acaba pasando.
     */
    public static function becauseThereIsNoMotivation(): self
    {
        return new self(
            'CLAIM_MOTIVATION_REQUIRED',
            FailureKind::INVALID,
            'Every decision carries a written motivation.',
        );
    }

    public static function becauseThatDecisionDoesNotExist(string $decision): self
    {
        return new self(
            'CLAIM_DECISION_UNKNOWN',
            FailureKind::INVALID,
            \sprintf('There is no decision called %s.', $decision),
        );
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function kind(): FailureKind
    {
        return $this->kind;
    }
}
