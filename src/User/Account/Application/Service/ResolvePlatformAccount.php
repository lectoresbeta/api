<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\User\Account\Application\Contract\PlatformAccount;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;

/**
 * Quién es la cuenta institucional (`FEAT-COM-038`).
 *
 * Una consulta y una marca. Si hubiera varias marcadas responde la primera:
 * dos cuentas institucionales no rompen ninguna invariante —publicarían las
 * dos— y hacer fallar la Home por eso sería convertir un descuido de
 * operación en una caída.
 */
final readonly class ResolvePlatformAccount implements PlatformAccount
{
    public function __construct(private UserRepository $users)
    {
    }

    public function id(): ?string
    {
        return $this->users->institutional()?->id()->value();
    }
}
