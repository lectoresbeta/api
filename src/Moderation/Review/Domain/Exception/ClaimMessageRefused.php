<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede escribir o leer eso (`FEAT-MOD-009`).
 */
final class ClaimMessageRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Para quien no es parte, **la reclamación no existe**. Un permiso
     * denegado le confirmaría que hay un expediente abierto, que es
     * información sobre otras personas.
     */
    public static function claimNotFound(): self
    {
        return new self('CLAIM_NOT_FOUND', FailureKind::NOT_FOUND, 'That claim does not exist.');
    }

    /**
     * Una parte **no abre conversación por su cuenta** (`RN-2`).
     *
     * Si pudiera, la cola del moderador se llenaría de alegatos no
     * solicitados y el expediente dejaría de ser un procedimiento para
     * convertirse en una bandeja de entrada.
     */
    public static function threadNotOpen(): self
    {
        return new self('THREAD_NOT_OPEN', FailureKind::CONFLICT, 'Moderation has not opened a conversation with you about this claim.');
    }

    /**
     * `RN-6`: el hilo se cierra al resolverse. Se puede leer, no continuar.
     */
    public static function claimResolved(): self
    {
        return new self('CLAIM_ALREADY_RESOLVED', FailureKind::CONFLICT, 'That claim is closed.');
    }

    public static function emptyMessage(): self
    {
        return new self('EMPTY_MESSAGE', FailureKind::INVALID, 'A message cannot be empty.');
    }

    public static function unknownParty(): self
    {
        return new self('UNKNOWN_THREAD_PARTY', FailureKind::INVALID, 'That is not a party to write to.');
    }

    /**
     * Una reclamación **sin persona señalada** no tiene con quién abrir el
     * segundo hilo: lo reclamado era una obra, no alguien.
     */
    public static function noSubject(): self
    {
        return new self('CLAIM_HAS_NO_SUBJECT', FailureKind::CONFLICT, 'That claim names nobody to write to.');
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
