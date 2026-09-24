<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Contract\AuthorAudience;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * `User`'s side of the privacy ceiling (`FEAT-USR-038`).
 *
 * `FOLLOWERS` answers **`false`, and that is the correct answer today, not a
 * fallback**: nobody can follow anybody yet
 * ([`FEAT-COM-010`](../../../../../docs/features/README.md) does not exist),
 * so the follower set of every author is empty and «only my followers» means
 * «nobody». When following arrives, this method asks `Community` for the set
 * and the sentence stops being a tautology — **one place changes**, which is
 * the whole reason the contract answers a boolean instead of handing the
 * setting over.
 *
 * Note which way it fails. If somebody forgets to come back here,
 * `FOLLOWERS` keeps behaving as `NOBODY`: a setting that is too strict, which
 * its owner notices and complains about. The other mistake — treating it as
 * `EVERYONE` — nobody notices, and that is the one that matters.
 */
final readonly class CheckAuthorAudience implements AuthorAudience
{
    public function __construct(private UserPrivacySettingsRepository $settings)
    {
    }

    public function acceptsCommentsFrom(string $authorId, string $readerId): bool
    {
        try {
            $settings = $this->settings->ofUser(UserId::fromString($authorId));
        } catch (InvalidValue) {
            return false;
        }

        // Sin fila, los valores por defecto explícitos (`RN-4`). No es lo
        // mismo que «entonces todo vale»: es la misma frase que se habría
        // guardado al crear la cuenta, dicha en voz alta.
        $permission = $settings?->commentPermission() ?? PrivacyAudience::EVERYONE;

        return match ($permission) {
            PrivacyAudience::EVERYONE => true,
            PrivacyAudience::FOLLOWERS => $this->follows($readerId, $authorId),
            PrivacyAudience::NOBODY => false,
        };
    }

    /**
     * Aquí irá la consulta al grafo de seguidores de `Community`. Mientras
     * nadie pueda seguir a nadie, el conjunto está vacío y la respuesta es
     * la correcta.
     */
    private function follows(string $readerId, string $authorId): bool
    {
        return false;
    }
}
