<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Query;

/**
 * Lo que el layout necesita en cualquier pantalla (`FEAT-USR-027`).
 *
 * Sin identificador de nadie más: solo el propio contexto, y no hay forma de
 * pedir el de otra persona (`RN-5`).
 */
final readonly class GetSessionContext
{
    public function __construct(public string $userId)
    {
    }
}
