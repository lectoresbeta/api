<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\EmailChangeLink;
use LectoresBeta\User\Account\Application\Contract\EmailChangeLinkProvider;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\EmailChangeRequestRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\EmailChangeRequestId;

/**
 * El lado de `User` del contrato (`FEAT-USR-040`).
 *
 * Acuña el secreto al enviar y no al pedir el cambio, igual que la activación
 * y la recuperación: así el plazo empieza cuando el correo sale y no mientras
 * espera en una cola de reintentos, y el valor en claro no pasa por ninguna
 * cola.
 *
 * Devuelve **las dos direcciones**, porque el flujo manda dos correos: el
 * enlace a la nueva y el aviso a la anterior. La anterior es la que la cuenta
 * sigue teniendo — la dirección no cambia hasta confirmar (`RN-2`).
 */
final readonly class IssueEmailChangeLink implements EmailChangeLinkProvider
{
    /**
     * Lo mismo que el enlace de activación (`S-22`): confirmar una dirección
     * nueva es la misma clase de prueba, y quien pide el cambio puede no
     * mirar ese buzón hasta el día siguiente.
     */
    private const LIFETIME = 'P2D';

    public function __construct(
        private UserRepository $users,
        private EmailChangeRequestRepository $requests,
        private SecureTokenFactory $secureTokens,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function issueFor(string $requestId): ?EmailChangeLink
    {
        try {
            $request = $this->requests->ofId(EmailChangeRequestId::fromString($requestId));
        } catch (InvalidValue) {
            return null;
        }

        if (null === $request || $request->isConsumed()) {
            return null;
        }

        $user = $this->users->ofId($request->userId());

        if (null === $user || AccountStatus::DELETED === $user->status()) {
            return null;
        }

        $now = $this->clock->now();
        $expiresAt = $now->add(new \DateInterval(self::LIFETIME));
        $secret = $this->secureTokens->create();

        $this->session->execute(function () use ($request, $secret, $expiresAt): void {
            $request->issueToken($secret->hash, $expiresAt);
            $this->requests->save($request);
        });

        return new EmailChangeLink(
            $request->newEmail()->value(),
            $user->email()->value(),
            $user->username()->value(),
            $secret->plain,
            $expiresAt,
        );
    }
}
