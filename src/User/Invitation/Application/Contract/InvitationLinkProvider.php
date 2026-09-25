<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Contract;

/**
 * El enlace de una invitación, pedido en el momento de enviar el correo
 * (`FEAT-USR-018`, `FEAT-NOT-007`).
 *
 * Existe por lo mismo que `ActivationLinkProvider`: el token es una
 * credencial viva y no puede viajar por la cola, pero el correo que lo
 * entrega es de `Notification`, que es quien tiene plantillas, proveedor y
 * entregabilidad.
 *
 * Así que **el hecho viaja asíncrono y el secreto se pide síncrono**, en el
 * momento de enviar. Y tiene el mismo segundo beneficio: el enlace empieza a
 * vivir cuando sale el correo, así que un envío que solo prospera tras horas
 * de reintentos sigue llevando un enlace usable.
 *
 * **Pregunta, no ordena.**
 */
interface InvitationLinkProvider
{
    /**
     * `null` cuando no hay nada que mandar: la invitación no existe, ya se
     * usó, o la dirección ya tiene cuenta. Quien llama lo trata como «hecho»,
     * no como error — es el desenlace normal de un hecho reentregado.
     */
    public function issueFor(string $invitationId): ?InvitationLink;
}
