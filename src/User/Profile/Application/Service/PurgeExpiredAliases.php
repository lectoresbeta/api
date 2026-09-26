<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Profile\Domain\Entity\UsernameAlias;
use LectoresBeta\User\Profile\Domain\Repository\UsernameAliasRepository;

/**
 * Retirar los alias de nombre de usuario ya caducados (`FEAT-USR-036`).
 *
 * **Es housekeeping, no lógica de negocio**, y el matiz es el punto entero de
 * la funcionalidad: un alias caducado **ya** ha dejado de resolver y **ya**
 * ha dejado de ocupar su nombre, lo borre alguien o no. Lo decide su fecha,
 * no este servicio.
 *
 * Si la disponibilidad de un nombre dependiera de que esto hubiera pasado, un
 * fallo del programador mantendría nombres bloqueados sin que nadie se
 * enterase, y el sistema daría respuestas distintas según la hora del día.
 * Saltarse una ejecución solo acumula filas.
 *
 * **Pregunta al dominio antes de borrar cada fila** (`RN-1`). La consulta que
 * las selecciona ya filtra por fecha, y volver a preguntárselo a la entidad
 * cuesta nada y protege de la única forma en que esto podría hacer daño: que
 * la consulta y la regla dejen de decir lo mismo.
 *
 * **Por lotes, y cada lote se confirma solo** (`RN-6`). Con acumulación, un
 * único `DELETE` gigante bloquearía la tabla por la que pasa cada resolución
 * de perfil; y un fallo a la mitad dejaría hecho lo hecho, que es lo que
 * hace falta de un proceso que se puede volver a lanzar.
 */
final readonly class PurgeExpiredAliases
{
    /**
     * Acotado para no bloquear la tabla, y repetido hasta agotar: el tope no
     * es cuántos se borran en total, es cuántos por transacción.
     */
    public const BATCH = 500;

    /**
     * Un tope de rondas, para que un fallo raro no deje el comando dando
     * vueltas para siempre. Con el lote de arriba son 50.000 filas por
     * ejecución; lo que sobre se borra mañana, que es inocuo.
     */
    private const MAX_BATCHES = 100;

    public function __construct(
        private UsernameAliasRepository $aliases,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    /**
     * @return int cuántos alias ha borrado, o borraría en una simulación
     */
    public function __invoke(bool $dryRun = false): int
    {
        $now = $this->clock->now();
        $purged = 0;

        for ($round = 0; $round < self::MAX_BATCHES; ++$round) {
            $batch = array_values(array_filter(
                $this->aliases->expiredAt($now, self::BATCH),
                // La regla es del dominio: el comando la invoca, no la
                // reimplementa.
                static fn (UsernameAlias $alias): bool => !$alias->isInForceAt($now),
            ));

            if ([] === $batch) {
                return $purged;
            }

            if ($dryRun) {
                // Contar y parar: sin borrar, la siguiente ronda devolvería
                // exactamente lo mismo y esto no acabaría nunca.
                return $purged + \count($batch);
            }

            $this->session->execute(function () use ($batch): void {
                foreach ($batch as $alias) {
                    $this->aliases->purge($alias);
                }
            });

            $purged += \count($batch);
        }

        return $purged;
    }
}
