<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Contract\AuthorAudience;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Repository\AuthorFollowerRepository;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * `User`'s side of the privacy ceiling (`FEAT-USR-038`).
 *
 * `FOLLOWERS` ya no es una tautología: desde `FEAT-COM-010` se puede seguir a
 * alguien, y la respuesta sale de la **copia local** del grafo. `User` no le
 * pregunta a `Community` en mitad de una respuesta, y no por comodidad — esta
 * clase *es* un contrato publicado, y
 * [`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)
 * prohíbe que un contrato llame al de otro contexto mientras responde. Como
 * `Community` ya llama a `RegisteredUsers` para dejar seguir a alguien, un
 * contrato en sentido contrario cerraría justo el ciclo que esa regla evita.
 *
 * La copia va **en diferido**: entre seguir a alguien y poder comentar sus
 * textos pasa lo que tarde la cola. Al revés no, y por eso el hecho de dejar
 * de seguir viaja por el mismo camino y no se olvida nunca.
 *
 * Lo que esto abre y decide producto (`C-22`): con seguimiento unilateral,
 * «solo mis seguidores» es «cualquiera que pulse Seguir». Hoy el ajuste hace
 * literalmente lo que dice.
 *
 * Y por encima de todo ello está **el bloqueo** (`FEAT-COM-034`): ninguna
 * combinación de ajustes le devuelve la palabra a quien ha sido bloqueado, ni
 * al revés. Se consulta la misma copia local, por la misma regla.
 */
final readonly class CheckAuthorAudience implements AuthorAudience
{
    public function __construct(
        private UserPrivacySettingsRepository $settings,
        private AuthorFollowerRepository $followers,
        private BlockedPairRepository $blocks,
    ) {
    }

    public function acceptsCommentsFrom(string $authorId, string $readerId): bool
    {
        try {
            $author = UserId::fromString($authorId);
            $settings = $this->settings->ofUser($author);
        } catch (InvalidValue) {
            return false;
        }

        // **Un bloqueo vence a cualquier ajuste** (`FEAT-COM-034`), y corta
        // en los dos sentidos: da igual quién bloqueó a quién, porque lo que
        // se decide aquí es si estas dos personas se hablan.
        if ($this->isBlocked($readerId, $author)) {
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

    private function isBlocked(string $readerId, UserId $author): bool
    {
        try {
            return $this->blocks->exists(UserId::fromString($readerId), $author);
        } catch (InvalidValue) {
            return false;
        }
    }

    /**
     * Un identificador ilegible no sigue a nadie. No es lo mismo que negar el
     * acceso por privacidad, pero la respuesta coincide, y es la prudente.
     */
    private function follows(string $readerId, string $authorId): bool
    {
        try {
            return $this->followers->follows(UserId::fromString($readerId), UserId::fromString($authorId));
        } catch (InvalidValue) {
            return false;
        }
    }
}
