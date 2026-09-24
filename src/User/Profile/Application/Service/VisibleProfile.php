<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Service;

use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;
use LectoresBeta\User\Profile\Application\DTO\PublicProfile;
use LectoresBeta\User\Profile\Domain\Enum\ProfileResolution;
use LectoresBeta\User\Profile\Domain\Exception\ProfileNotFound;

/**
 * Un perfil, si es que hay perfil que enseñar (`FEAT-USR-014`,
 * `FEAT-USR-038`).
 *
 * Dos puertas, y las dos responden lo mismo al cerrarse:
 *
 * 1. **una cuenta eliminada no tiene perfil** (`RN-7`). Está anonimizada: no
 *    queda nada que enseñar, solo un identificador al que siguen apuntando
 *    correcciones y movimientos;
 * 2. **el ajuste de privacidad de su titular manda** (`FEAT-USR-038`). En
 *    `NOBODY` la cuenta se vuelve invisible, que es exactamente lo que ese
 *    ajuste promete.
 *
 * **Su titular siempre se ve a sí mismo.** Esconderle su propio perfil sería
 * absurdo, y es lo que hace que el ajuste se pueda deshacer: quien lo cierra
 * sigue entrando a abrirlo.
 *
 * `FOLLOWERS` oculta hoy como `NOBODY`, y es la respuesta correcta mientras
 * nadie pueda seguir a nadie: el conjunto de seguidores está vacío. Cuando
 * exista el grafo, aquí se comparará al visitante contra él.
 */
final readonly class VisibleProfile
{
    public function __construct(private UserPrivacySettingsRepository $privacy)
    {
    }

    public function of(User $user, ?string $viewerId, ProfileResolution $via, string $asked): PublicProfile
    {
        if ($user->status()->isDeleted()) {
            throw ProfileNotFound::create();
        }

        if (!$this->isVisibleTo($user, $viewerId)) {
            throw ProfileNotFound::create();
        }

        return new PublicProfile(
            $user->id()->value(),
            $asked,
            $user->username()->value(),
            $via->value,
            $user->name()?->value(),
            $user->description(),
            $user->avatarUrl(),
            $user->coverUrl(),
        );
    }

    private function isVisibleTo(User $user, ?string $viewerId): bool
    {
        if (null !== $viewerId && $viewerId === $user->id()->value()) {
            return true;
        }

        // Sin fila de ajustes, el valor por defecto explícito (`RN-4`). No es
        // «entonces se ve»: es la misma frase que se habría guardado al crear
        // la cuenta.
        $visibility = $this->privacy->ofUser($user->id())?->profileVisibility() ?? PrivacyAudience::EVERYONE;

        return PrivacyAudience::EVERYONE === $visibility;
    }
}
