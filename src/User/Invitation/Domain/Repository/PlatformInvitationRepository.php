<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Invitation\Domain\Entity\PlatformInvitation;
use LectoresBeta\User\Invitation\Domain\ValueObject\PlatformInvitationId;

interface PlatformInvitationRepository
{
    public function ofId(PlatformInvitationId $id): ?PlatformInvitation;

    /**
     * La invitación de ese token, **buscada por su hash**.
     *
     * El token en claro no está en ninguna parte: viaja en el correo y se
     * olvida. Quien pueda leer la tabla no puede usar los enlaces.
     */
    public function ofTokenHash(string $tokenHash): ?PlatformInvitation;

    /**
     * Las que ha mandado esa persona, de la más reciente a la más antigua.
     *
     * @return list<PlatformInvitation>
     */
    public function sentBy(UserId $inviterId, int $limit, int $offset): array;

    /**
     * Cuántas ha mandado **desde** una fecha.
     *
     * Es el tope diario (`RN-4`): sin él, invitar es un canal de correo
     * gratuito hacia direcciones ajenas con el remitente de la plataforma.
     */
    public function sentCountSince(UserId $inviterId, \DateTimeImmutable $since): int;

    /**
     * Si esa persona ya tiene una invitación viva a esa dirección.
     *
     * Reinvitar a quien ya invitaste no crea una segunda: la primera sigue
     * valiendo, y mandar dos correos iguales es spam con buena intención.
     */
    public function liveTo(UserId $inviterId, string $email): ?PlatformInvitation;

    public function save(PlatformInvitation $invitation): void;
}
