<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Repository\AuthorFollowerRepository;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;
use LectoresBeta\User\Profile\Application\DTO\AuthorCard;
use LectoresBeta\User\Profile\Application\Query\SearchAuthors;
use LectoresBeta\User\Profile\Domain\Exception\ProfileNotFound;

/**
 * Buscar personas (`FEAT-USR-017`).
 *
 * **La consulta trae candidatos y la privacidad decide cuáles sobreviven.**
 * Es el orden que importa: filtrar en SQL haría la consulta ilegible y
 * repartiría por ella reglas —el ajuste de perfil, el bloqueo en los dos
 * sentidos— que ya viven en `Privacy` y que aquí solo se aplican.
 *
 * Sin `RN-3` y `RN-4` esto sería la forma cómoda de saltarse todos los
 * ajustes de privacidad de la plataforma: quien se esconde de un perfil
 * aparecería en un listado.
 *
 * Se piden **más candidatos de los que se devuelven** porque algunos caerán
 * al filtrar, y una página que se queda a la mitad por culpa de tres perfiles
 * cerrados parece un error.
 */
final readonly class SearchAuthorsHandler
{
    private const OVERFETCH = 3;

    public function __construct(
        private UserRepository $users,
        private UserPrivacySettingsRepository $privacy,
        private AuthorFollowerRepository $followers,
        private BlockedPairRepository $blocks,
    ) {
    }

    /**
     * @return list<AuthorCard>
     */
    public function __invoke(SearchAuthors $query): array
    {
        try {
            $viewer = UserId::fromString($query->viewerId);
        } catch (InvalidValue) {
            throw ProfileNotFound::create();
        }

        $limit = max(1, min($query->limit, 20));
        $term = null === $query->term || '' === trim($query->term) ? null : trim($query->term);

        if (null === $term && [] === $query->genres) {
            // `RN-7`. Un buscador que con la caja vacía devuelve el padrón
            // entero **es** el padrón entero.
            return [];
        }

        $candidates = $this->users->discoverable($term, $query->genres, min($limit * self::OVERFETCH, 100));

        if ([] === $candidates) {
            return [];
        }

        $ids = array_map(static fn (User $user): string => $user->id()->value(), $candidates);
        $visibility = $this->privacy->profileVisibilityOf($ids);
        $blocked = array_flip($this->blocks->blockedAmong($viewer, $ids));
        $followed = array_flip($this->followers->followedAmong($viewer, $ids));

        $cards = [];

        foreach ($candidates as $candidate) {
            $id = $candidate->id()->value();

            if (isset($blocked[$id])) {
                continue;
            }

            if (!$this->visibleTo($viewer, $id, $visibility[$id] ?? PrivacyAudience::EVERYONE, $followed)) {
                continue;
            }

            $cards[] = new AuthorCard(
                $id,
                $candidate->username()->value(),
                $candidate->name()?->value(),
                $candidate->avatarUrl(),
            );

            if (\count($cards) === $limit) {
                break;
            }
        }

        return $cards;
    }

    /**
     * @param array<string, int> $followed
     */
    private function visibleTo(UserId $viewer, string $candidateId, PrivacyAudience $audience, array $followed): bool
    {
        if ($viewer->value() === $candidateId) {
            // `RN-5`. Esconderle a alguien su propia ficha sería raro y no
            // protege nada.
            return true;
        }

        return match ($audience) {
            PrivacyAudience::EVERYONE => true,
            PrivacyAudience::FOLLOWERS => isset($followed[$candidateId]),
            PrivacyAudience::NOBODY => false,
        };
    }
}
