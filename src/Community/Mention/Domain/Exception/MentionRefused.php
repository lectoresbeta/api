<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede mencionar así (`FEAT-COM-032`).
 *
 * Una mención a alguien que no existe **se rechaza**, no se ignora: quien
 * escribe cree que ha nombrado a una persona, y guardar el texto sin la
 * mención le enseñaría el resultado cuando ya no puede corregirlo.
 */
final class MentionRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unknownUser(): self
    {
        return new self('MENTIONED_USER_NOT_FOUND', 'One of the mentioned accounts does not exist.');
    }

    /**
     * Un tope, y está aquí para lo que se ve venir: sin él, una publicación
     * con doscientas menciones es un envío masivo de avisos que cualquiera
     * puede disparar (`I-11`).
     */
    public static function tooMany(int $limit): self
    {
        return new self('TOO_MANY_MENTIONS', \sprintf('A post or comment mentions at most %d people.', $limit));
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
