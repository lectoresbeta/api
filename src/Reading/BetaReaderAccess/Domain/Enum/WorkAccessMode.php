<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\Enum;

/**
 * Cómo está abierta una obra, tal y como lo entiende `Reading`.
 *
 * **Es una copia deliberada del vocabulario de `Work`**, no un descuido.
 * `Work` posee la modalidad y la publica como cadena en su contrato; lo que
 * cada modalidad *significa para entrar* es regla de este contexto, y una
 * regla no se escribe sobre un enum ajeno. Es el mismo reparto que hay entre
 * las etiquetas de contenido de `Work` y la copia que `User` guarda para el
 * filtro del lector.
 *
 * El precio de la copia es que añadir una modalidad obliga a tocar los dos
 * sitios. El precio de no copiarla sería que `Reading` dependiese del modelo
 * interno de `Work`, que es lo que `deptrac.contexts.yaml` impide.
 *
 * Una modalidad que este contexto no conoce **no admite nada**: ante un valor
 * futuro que no entiende, lo prudente es no dejar entrar a nadie.
 */
enum WorkAccessMode: string
{
    case PUBLIC = 'PUBLIC';
    case ON_REQUEST = 'ON_REQUEST';
    case PRIVATE = 'PRIVATE';

    /**
     * Solo `ON_REQUEST` admite solicitudes, y las otras dos las rechazan por
     * motivos opuestos: en `PUBLIC` no hace falta pedir nada —basta con
     * ponerse a corregir (`FEAT-RDG-001`)— y en `PRIVATE` pedir no es la vía
     * de entrada, que es la invitación.
     */
    public function takesRequests(): bool
    {
        return self::ON_REQUEST === $this;
    }

    /**
     * Invitar, en cambio, vale en cualquiera de las tres (`FEAT-RDG-004`
     * `RN-3`). La asimetría es deliberada: una solicitud le pide trabajo al
     * autor, y una invitación es el autor decidiendo hacerlo.
     */
    public function takesInvitations(): bool
    {
        return true;
    }
}
