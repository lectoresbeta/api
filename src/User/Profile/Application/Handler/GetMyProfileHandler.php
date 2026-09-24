<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Profile\Application\DTO\EditableProfile;
use LectoresBeta\User\Profile\Application\Query\GetMyProfile;
use LectoresBeta\User\Profile\Application\Service\MyProfile;

/**
 * Lo que la pantalla de «Configuración › Perfil» necesita para abrirse
 * (`FEAT-USR-008`).
 */
final readonly class GetMyProfileHandler
{
    public function __construct(
        private MyProfile $profile,
        private Clock $clock,
    ) {
    }

    public function __invoke(GetMyProfile $query): EditableProfile
    {
        return MyProfile::asView($this->profile->of($query->userId), $this->clock->now());
    }
}
