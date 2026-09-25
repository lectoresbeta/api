<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * El contrato publicado para saber a qué dirección escribir
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Existe para los avisos operativos que **no llevan enlace**: contraseña
 * cambiada, correo cambiado, cuenta bloqueada. Los que sí lo llevan lo piden
 * por su propio contrato —`ActivationLinkProvider`,
 * `PasswordResetLinkProvider`— y reciben la dirección de paso, porque emitir
 * la credencial y saber dónde mandarla son la misma operación.
 *
 * **Es la puerta más delicada de este contexto**, y conviene decir por qué se
 * abre: una dirección de correo es un dato personal, y un contrato que las
 * reparte es un contrato que hay que justificar cada vez que alguien lo usa.
 * La justificación aquí es que el correo operativo **es** la defensa de la
 * cuenta: sin él, a quien le roban una no se entera.
 *
 * Por eso devuelve una dirección y no una lista, y por eso no hay forma de
 * buscar por correo: se pregunta por una cuenta concreta que ya se conoce, no
 * se recorre el censo.
 *
 * Una cuenta eliminada no tiene dirección a la que escribir: `null`.
 */
interface MailingAddresses
{
    public function ofUser(string $userId): ?MailingAddress;
}
