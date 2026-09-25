<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Repository\AuthorFollowerRepository;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;
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
 * `FOLLOWERS` enseña el perfil **a quien sigue a su titular** desde
 * `FEAT-COM-010`. Sale de la copia local del grafo, alimentada por los hechos
 * de `Community`, así que va en diferido: quien acaba de seguir a alguien
 * puede tardar un momento en ver su perfil.
 *
 * Conviene saber lo que ese ajuste promete de verdad, porque seguir es
 * **unilateral**: «solo mis seguidores» es, en la práctica, «cualquiera que
 * pulse Seguir». Está anotado como `C-22` y lo decide producto; mientras
 * tanto el ajuste hace literalmente lo que dice.
 *
 * El estado de la relación que devuelve sale de esa misma copia local, y eso
 * es deliberado: es la que esta petición ya está usando para decidir si
 * enseña el perfil, así que preguntar en otro sitio podría dar dos respuestas
 * distintas sobre lo mismo dentro de una sola respuesta HTTP.
 */
final readonly class VisibleProfile
{
    public function __construct(
        private UserPrivacySettingsRepository $privacy,
        private AuthorFollowerRepository $followers,
        private BlockedPairRepository $blocks,
    ) {
    }

    public function of(User $user, ?string $viewerId, ProfileResolution $via, string $asked): PublicProfile
    {
        if ($user->status()->isDeleted()) {
            throw ProfileNotFound::create();
        }

        if (!$this->isVisibleTo($user, $viewerId)) {
            throw ProfileNotFound::create();
        }

        $viewer = $this->identify($viewerId);

        return new PublicProfile(
            $user->id()->value(),
            $asked,
            $user->username()->value(),
            $via->value,
            $user->name()?->value(),
            $user->description(),
            $user->avatarUrl(),
            $user->coverUrl(),
            // Nulos sin sesión: no hay relación que contar con un visitante
            // anónimo, y un `false` diría que no le sigue, que es otra cosa.
            null === $viewer ? null : $this->followers->follows($viewer, $user->id()),
            null === $viewer ? null : $this->followers->follows($user->id(), $viewer),
            null === $viewer ? null : $this->blocks->exists($viewer, $user->id()),
        );
    }

    private function identify(?string $viewerId): ?UserId
    {
        if (null === $viewerId) {
            return null;
        }

        try {
            return UserId::fromString($viewerId);
        } catch (InvalidValue) {
            return null;
        }
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

        return match ($visibility) {
            PrivacyAudience::EVERYONE => true,
            PrivacyAudience::FOLLOWERS => null !== $viewerId && $this->follows($viewerId, $user->id()),
            PrivacyAudience::NOBODY => false,
        };
    }

    private function follows(string $viewerId, UserId $ownerId): bool
    {
        try {
            return $this->followers->follows(UserId::fromString($viewerId), $ownerId);
        } catch (InvalidValue) {
            return false;
        }
    }
}
