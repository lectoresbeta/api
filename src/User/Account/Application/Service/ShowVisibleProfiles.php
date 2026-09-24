<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;
use LectoresBeta\User\Account\Application\Contract\VisibleProfiles;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Repository\AuthorFollowerRepository;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * `User`'s side of «quién puede ver a quién» (`FEAT-COM-027`).
 *
 * Es la misma regla que aplica `VisibleProfile` para un perfil suelto, dicha
 * para un lote: cuenta viva, y el ajuste de su titular. **Vive aquí** porque
 * es la única forma de que se cumpla siempre — un contexto que recibiera los
 * perfiles y tuviera que filtrarlos después es un contexto que algún día no
 * lo hace.
 *
 * Tres consultas para una página entera, y ninguna por fila: los perfiles,
 * sus ajustes y —solo para los que dependan de ello— a quién sigue quien
 * pregunta.
 */
final readonly class ShowVisibleProfiles implements VisibleProfiles, ProfileCards
{
    public function __construct(
        private UserRepository $users,
        private UserPrivacySettingsRepository $privacy,
        private AuthorFollowerRepository $followers,
    ) {
    }

    /**
     * Las mismas tarjetas **sin el filtro**, para quien ya sabe quiénes son
     * (`ProfileCards`). Comparte implementación con `visibleTo()` a
     * propósito: son la misma consulta con una decisión distinta, y tenerlas
     * en dos clases sería dos sitios donde arreglar el día que la tarjeta
     * cambie.
     */
    public function of(array $userIds): array
    {
        return $this->cardsOf($userIds, null, filtered: false);
    }

    public function visibleTo(?string $viewerId, array $userIds): array
    {
        return $this->cardsOf($userIds, $viewerId, filtered: true);
    }

    /**
     * @param list<string> $userIds
     *
     * @return array<string, DirectoryEntry>
     */
    private function cardsOf(array $userIds, ?string $viewerId, bool $filtered): array
    {
        $wanted = array_values(array_unique(array_filter($userIds, static fn (string $id): bool => '' !== $id)));

        if ([] === $wanted) {
            return [];
        }

        // Una cuenta eliminada está anonimizada: no queda nada que enseñar
        // (`FEAT-USR-014` `RN-7`).
        $found = array_values(array_filter(
            $this->users->ofIds($wanted),
            static fn (User $user): bool => !$user->status()->isDeleted(),
        ));

        $visibility = !$filtered ? [] : $this->privacy->profileVisibilityOf(array_map(
            static fn (User $user): string => $user->id()->value(),
            $found,
        ));

        $following = !$filtered ? [] : $this->followedAmong($viewerId, $found, $visibility);

        $cards = [];

        foreach ($found as $user) {
            $id = $user->id()->value();

            if ($filtered && !self::isVisible($id, $viewerId, $visibility[$id] ?? PrivacyAudience::EVERYONE, $following)) {
                continue;
            }

            $cards[$id] = new DirectoryEntry(
                $id,
                $user->username()->value(),
                $user->name()?->value(),
                $user->avatarUrl(),
            );
        }

        return $cards;
    }

    /**
     * A quién de estos sigue quien pregunta, **preguntado solo por los que lo
     * necesitan**: los que tengan el perfil en `FOLLOWERS`. Para el resto la
     * respuesta no cambia nada, y el grafo es lo más caro de consultar.
     *
     * @param list<User>                     $found
     * @param array<string, PrivacyAudience> $visibility
     *
     * @return array<string, true>
     */
    private function followedAmong(?string $viewerId, array $found, array $visibility): array
    {
        if (null === $viewerId) {
            return [];
        }

        $restricted = array_values(array_filter(
            array_map(static fn (User $user): string => $user->id()->value(), $found),
            static fn (string $id): bool => PrivacyAudience::FOLLOWERS === ($visibility[$id] ?? PrivacyAudience::EVERYONE),
        ));

        if ([] === $restricted) {
            return [];
        }

        try {
            $viewer = UserId::fromString($viewerId);
        } catch (InvalidValue) {
            return [];
        }

        return array_fill_keys($this->followers->followedAmong($viewer, $restricted), true);
    }

    /**
     * @param array<string, true> $following
     */
    private static function isVisible(string $userId, ?string $viewerId, PrivacyAudience $visibility, array $following): bool
    {
        // Su titular siempre se ve a sí mismo, por lo mismo que ve su propio
        // perfil: esconderle lo suyo sería absurdo.
        if ($userId === $viewerId) {
            return true;
        }

        return match ($visibility) {
            PrivacyAudience::EVERYONE => true,
            PrivacyAudience::FOLLOWERS => isset($following[$userId]),
            PrivacyAudience::NOBODY => false,
        };
    }
}
