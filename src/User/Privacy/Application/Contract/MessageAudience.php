<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Contract;

/**
 * Si esta persona admite que aquella le abra una conversación
 * (`FEAT-USR-010`).
 *
 * **Un booleano, nunca el ajuste.** Entregar `FOLLOWERS` obligaría a quien
 * pregunta a aprender qué significa «seguidores» aquí e irlo a resolver por
 * su cuenta, y entonces la regla viviría en dos sitios con dos respuestas.
 *
 * Está separada de [`AuthorAudience`](AuthorAudience.php) a propósito, aunque
 * las dos salgan del mismo ajuste de privacidad: aquella habla de la
 * audiencia de los **textos** de un autor y esta de su **buzón**. Una puerta,
 * una pregunta; un contrato que respondiera las dos obligaría a quien solo
 * necesita una a depender también de la otra.
 *
 * Responde sobre **abrir**, no sobre continuar. Que endurecer el ajuste no
 * cierre los hilos ya abiertos es una decisión de `FEAT-USR-010` `RN-5`, y
 * quien la aplica es `Community`: aquí no se sabe si esos dos ya se hablaban.
 */
interface MessageAudience
{
    public function acceptsMessagesFrom(string $recipientId, string $senderId): bool;
}
