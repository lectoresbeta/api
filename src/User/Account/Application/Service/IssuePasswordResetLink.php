<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\PasswordResetLink;
use LectoresBeta\User\Account\Application\Contract\PasswordResetLinkProvider;
use LectoresBeta\User\Account\Domain\Entity\PasswordResetToken;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\PasswordResetTokenRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\PasswordResetTokenId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * El lado de `User` del contrato (`FEAT-USR-007`).
 *
 * Se emite aquí y no al pedirlo, que es el mismo motivo que en la activación:
 * un token creado al recibir la petición empezaría a caducar mientras el
 * correo espera en una cola de reintentos. Con **una hora** de vida (`RN-6`)
 * eso no es un detalle — un reintento de media hora se comería medio plazo.
 *
 * Antes de emitir se invalida todo lo vivo (`RN-8`). Dos enlaces a la vez
 * doblarían la ventana en la que uno filtrado sigue sirviendo para quedarse
 * con la cuenta.
 *
 * Una cuenta **sin activar sí recibe enlace**, y es deliberado (`RN-14`):
 * usarlo demuestra que ese buzón es suyo, que es exactamente lo que demuestra
 * activar. Una eliminada no, porque ahí no hay nada que recuperar.
 */
final readonly class IssuePasswordResetLink implements PasswordResetLinkProvider
{
    /**
     * Una hora. Un enlace de activación abre una cuenta recién creada y
     * vacía; este se queda con una que ya tiene historial, créditos y obra
     * inédita, así que su ventana debe ser la menor que siga siendo usable.
     */
    private const LIFETIME = 'PT1H';

    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
        private SecureTokenFactory $secureTokens,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function issueFor(string $userId): ?PasswordResetLink
    {
        try {
            $user = $this->users->ofId(UserId::fromString($userId));
        } catch (InvalidValue) {
            return null;
        }

        if (null === $user || AccountStatus::DELETED === $user->status()) {
            return null;
        }

        $now = $this->clock->now();
        $expiresAt = $now->add(new \DateInterval(self::LIFETIME));
        $secret = $this->secureTokens->create();

        $this->session->execute(function () use ($user, $secret, $now, $expiresAt): void {
            foreach ($this->tokens->liveTokensOf($user->id()) as $previous) {
                $previous->invalidate($now);
                $this->tokens->save($previous);
            }

            $this->tokens->save(new PasswordResetToken(
                PasswordResetTokenId::generate(),
                $user->id(),
                $secret->hash,
                $now,
                $expiresAt,
            ));
        });

        return new PasswordResetLink(
            $user->email()->value(),
            $user->username()->value(),
            $secret->plain,
            $expiresAt,
        );
    }
}
