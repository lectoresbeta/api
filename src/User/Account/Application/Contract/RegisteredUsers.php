<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * `User`'s published contract for the one question another context needs to
 * ask about somebody it did not find itself
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **A boolean, and nothing else.** Not a name, not a profile, not whether the
 * account is activated: what the caller is deciding is whether an identifier
 * refers to a real person, and any extra field would be somebody's data
 * crossing a border for no reason.
 *
 * It exists for `FEAT-RDG-004`: inviting an invented identifier would create
 * an invitation nobody can accept and a notice that cannot be delivered.
 *
 * A deleted account answers `false`, which is the right answer — the
 * invitation would go nowhere.
 */
interface RegisteredUsers
{
    public function exists(string $userId): bool;

    /**
     * Si además está **activada**.
     *
     * Existe aparte de `exists()` porque responden a preguntas distintas:
     * aquella basta para no mandar una invitación al vacío, y esta hace falta
     * cuando lo que se va a conceder tiene consecuencias — el rol de
     * moderador, por ejemplo (`FEAT-MOD-004`). Una cuenta sin activar no ha
     * demostrado todavía que haya alguien detrás.
     */
    public function isActivated(string $userId): bool;

    /**
     * Quién es la cuenta de esa dirección, si la hay.
     *
     * **Es la única pregunta de este contrato que va del correo hacia la
     * cuenta**, y se abre para un solo uso: el comando de consola que nombra
     * al primer administrador ([`FEAT-MOD-012`](../../../../../docs/features/moderation/FEAT-MOD-012-bootstrap-admin.md)),
     * donde quien la ejecuta escribe una dirección porque es lo que conoce.
     *
     * No debe usarse desde ningún endpoint: responder si una dirección tiene
     * cuenta es exactamente lo que el alta, el reenvío de activación y la
     * recuperación de contraseña se cuidan de no decir.
     */
    public function idOfEmail(string $email): ?string;
}
