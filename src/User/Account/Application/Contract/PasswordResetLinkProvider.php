<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * El contrato publicado de `User` para emitir un enlace de recuperación
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Existe por lo mismo que `ActivationLinkProvider`: el token no puede viajar
 * por la cola, y el correo que lo lleva es de `Notification`, que es quien
 * tiene las plantillas, la entregabilidad y el proveedor.
 *
 * Así que **el hecho viaja asíncrono y el secreto se pide en el momento de
 * enviar**. Tiene un segundo beneficio que conviene nombrar: el enlace
 * empieza a caducar cuando sale el correo, no cuando se pidió, y con una hora
 * de vida (`RN-6`) esa diferencia es la mitad del plazo si el envío espera en
 * un reintento.
 *
 * Pregunta, no manda. Emitir un enlace no cambia nada que otro contexto pueda
 * observar: crea una credencial y devuelve lo justo para entregarla.
 */
interface PasswordResetLinkProvider
{
    /**
     * Emite un enlace nuevo e invalida el anterior, de forma que **solo vale
     * el del último correo** (`RN-8`).
     *
     * Devuelve `null` cuando no hay nada que mandar: la cuenta no existe o
     * está eliminada. Quien llama lo trata como «hecho» y no como un error —
     * es el desenlace normal de un evento reentregado.
     */
    public function issueFor(string $userId): ?PasswordResetLink;
}
