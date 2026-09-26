<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Infrastructure\Http;

use LectoresBeta\Community\Mention\Application\DTO\MentionInput;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;

/**
 * Las menciones tal y como llegan en el cuerpo de una petición
 * (`FEAT-COM-032` `RN-7`).
 *
 * Cada una es **un identificador y una posición**, nunca un nombre. Lo que el
 * usuario escribió se queda en el texto; a quién señaló lo dice aparte, y el
 * servidor lo comprueba. Si se resolviera leyendo el texto, cualquiera podría
 * fabricar una mención que pareciera apuntar a otra persona.
 *
 * Una entrada sin `userId` se descarta aquí en vez de llegar al caso de uso:
 * es transporte mal formado, no una decisión de negocio.
 */
final readonly class MentionsInBody
{
    /**
     * @return list<MentionInput>
     */
    public static function of(JsonBody $body): array
    {
        $mentions = [];

        foreach ($body->objectList('mentions') as $mention) {
            $userId = $mention->string('userId');

            if (null === $userId || '' === $userId) {
                continue;
            }

            $mentions[] = new MentionInput($userId, $mention->int('position') ?? 0);
        }

        return $mentions;
    }
}
