<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Contract\MessageAudience;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;
use LectoresBeta\User\Privacy\Domain\Repository\AuthorFollowerRepository;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * La puerta del buzón (`FEAT-USR-010`).
 *
 * Hermana de `CheckAuthorAudience` y con sus mismas dos cautelas, que aquí
 * valen igual:
 *
 * - **el bloqueo vence a cualquier ajuste**, y corta en los dos sentidos: da
 *   igual quién bloqueó a quién, porque lo que se decide es si estas dos
 *   personas se hablan;
 * - **`FOLLOWERS` sale de la copia local del grafo**, no de preguntarle a
 *   `Community`. Un contrato publicado no llama al de otro contexto mientras
 *   responde ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)),
 *   y como `Community` ya llama aquí para dejar seguir a alguien, la llamada
 *   de vuelta cerraría justo el ciclo que esa regla evita.
 *
 * `FOLLOWERS` significa **quien sigue al destinatario**, no al revés.
 * Conviene decirlo porque seguir es unilateral y es fácil leerlo al
 * contrario: el ajuste lo pone quien recibe, y lo que le importa es quién le
 * sigue a él.
 */
final readonly class CheckMessageAudience implements MessageAudience
{
    public function __construct(
        private UserPrivacySettingsRepository $settings,
        private AuthorFollowerRepository $followers,
        private BlockedPairRepository $blocks,
    ) {
    }

    public function acceptsMessagesFrom(string $recipientId, string $senderId): bool
    {
        try {
            $recipient = UserId::fromString($recipientId);
            $sender = UserId::fromString($senderId);
        } catch (InvalidValue) {
            return false;
        }

        if ($recipient->equals($sender)) {
            // Nadie se escribe a sí mismo (`FEAT-COM-011` `RN-2`). Se
            // responde que no aquí además de arriba porque un «sí» a esta
            // pregunta sería raro de leer.
            return false;
        }

        if ($this->blocks->exists($sender, $recipient)) {
            return false;
        }

        // Sin fila, los valores por defecto explícitos (`FEAT-USR-038`
        // `RN-4`). No es lo mismo que «entonces todo vale»: es la misma frase
        // que se habría guardado al crear la cuenta, dicha en voz alta.
        $permission = $this->settings->ofUser($recipient)?->messagePermission() ?? PrivacyAudience::EVERYONE;

        return match ($permission) {
            PrivacyAudience::EVERYONE => true,
            PrivacyAudience::FOLLOWERS => $this->followers->follows($sender, $recipient),
            PrivacyAudience::NOBODY => false,
        };
    }
}
