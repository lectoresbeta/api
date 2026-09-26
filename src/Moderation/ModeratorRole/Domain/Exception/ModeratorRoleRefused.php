<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede conceder ni revocar ese rol (`FEAT-MOD-004` `RN-1`).
 *
 * Cubre tres casos con un solo código: la cuenta no existe, no está activada,
 * o es la propia de quien lo pide. **Los tres son errores de quien administra
 * y no de nadie más**, así que no hay nada que proteger distinguiéndolos —
 * pero tampoco nada que ganar, porque el mensaje ya dice cuál es.
 */
final class ModeratorRoleRefused extends \DomainException implements BusinessFailure
{
    public static function becauseTheAccountIsNotUsable(): self
    {
        return new self('That account cannot hold a moderation role: it does not exist or is not activated.');
    }

    /**
     * Nadie se concede ni se quita el rol a sí mismo. Concedérselo haría del
     * registro de auditoría un trámite; quitárselo dejaría la plataforma sin
     * administrador por un descuido.
     */
    public static function becauseItIsYourOwnAccount(): self
    {
        return new self('A moderation role cannot be granted to or revoked from your own account.');
    }

    /**
     * Un nivel que no existe en el catálogo. Se distingue de los demás
     * porque es un error de forma y no de estado: quien llama ha escrito mal
     * el valor, y decírselo le ahorra buscar.
     */
    public static function becauseThatLevelDoesNotExist(string $level): self
    {
        return new self(\sprintf('There is no moderation level called %s.', $level));
    }

    public function errorCode(): string
    {
        return 'MODERATOR_ROLE_REFUSED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
