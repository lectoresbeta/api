<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * La cuenta con la que habla la plataforma (`FEAT-COM-038`).
 *
 * **Pregunta, nunca manda**: devuelve un identificador y nada más. No designa
 * la cuenta, no publica en su nombre y no dice nada de ella — quien quiera
 * pintarla usa las tarjetas de perfil, como con cualquier otra persona, que
 * es justo la gracia de que sea una cuenta normal.
 */
interface PlatformAccount
{
    /**
     * Quién es, o `null` si nadie ha designado ninguna.
     *
     * `null` es un estado normal y no una avería: una instalación recién
     * puesta en marcha no tiene cuenta institucional hasta que alguien la
     * designa por consola.
     */
    public function id(): ?string;
}
