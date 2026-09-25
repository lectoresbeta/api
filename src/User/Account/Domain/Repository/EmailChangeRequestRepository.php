<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Repository;

use LectoresBeta\User\Account\Domain\Entity\EmailChangeRequest;
use LectoresBeta\User\Account\Domain\ValueObject\EmailChangeRequestId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

interface EmailChangeRequestRepository
{
    public function save(EmailChangeRequest $request): void;

    public function ofId(EmailChangeRequestId $id): ?EmailChangeRequest;

    /**
     * Se busca por hash y nunca por el token: el valor en claro existe solo
     * dentro del correo que va a la dirección **nueva**.
     */
    public function ofTokenHash(string $tokenHash): ?EmailChangeRequest;

    /**
     * La solicitud viva de esa cuenta, si la hay. Solo puede haber una
     * (`FEAT-USR-040` `RN-6`), y de eso se encarga además un índice único
     * parcial en la base de datos.
     */
    public function pendingOf(UserId $userId): ?EmailChangeRequest;
}
