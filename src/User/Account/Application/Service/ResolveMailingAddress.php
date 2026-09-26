<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\MailingAddress;
use LectoresBeta\User\Account\Application\Contract\MailingAddresses;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * El lado de `User` del contrato.
 *
 * Una cuenta eliminada devuelve `null`: su dirección ya no es sitio al que
 * escribir, y un aviso operativo sobre una cuenta que no existe solo puede
 * confundir a quien lo reciba.
 *
 * Una **bloqueada** sí responde, y es deliberado: sigue habiendo una persona
 * detrás a la que avisar de que le han cambiado la contraseña, y que no pueda
 * escribir en la plataforma no significa que haya dejado de importarle su
 * cuenta.
 */
final readonly class ResolveMailingAddress implements MailingAddresses
{
    public function __construct(private UserRepository $users)
    {
    }

    public function ofUser(string $userId): ?MailingAddress
    {
        try {
            $user = $this->users->ofId(UserId::fromString($userId));
        } catch (InvalidValue) {
            return null;
        }

        if (null === $user || AccountStatus::DELETED === $user->status()) {
            return null;
        }

        return new MailingAddress($user->email()->value(), $user->username()->value());
    }
}
