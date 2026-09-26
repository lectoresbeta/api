<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\EventProcessing\Application\Service;

use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;

/**
 * Retirar del registro de deduplicación lo que ya no puede servir
 * (`FEAT-CRD-011` `RN-7`).
 *
 * **Sin esto la tabla crece con cada evento que recibe `Credits`, para
 * siempre.** Es la clase de crecimiento que no molesta durante dos años y
 * luego obliga a una migración con la aplicación parada.
 *
 * **180 días**, y el número no cubre lo que parece. La ventana de reentrega
 * real de RabbitMQ se mide en minutos: los reintentos del transporte se
 * agotan en segundos, y para eso sobraría cualquier margen. Lo que cubre es
 * el caso lento — un mensaje que cayó al transporte de fallos, se quedó ahí
 * semanas y alguien lo reprocesa a mano. Pasado ese plazo, reprocesar un
 * evento antiguo es una operación deliberada que **debe fallar de forma
 * visible**, no aplicarse en silencio.
 *
 * **Por lotes, y cada lote se confirma solo.** Esta es la tabla que cada
 * consumidor lee antes de mover un crédito: un único `DELETE` gigante la
 * bloquearía entera, y un fallo a la mitad desharía el trabajo ya hecho.
 *
 * Saltarse una ejecución es inocuo: solo se acumulan filas, y ninguna de
 * ellas hace daño mientras esté. Es lo contrario de un proceso del que
 * dependa una regla de negocio.
 */
final readonly class PurgeProcessedEvents
{
    /**
     * `RN-7`. Vive aquí y no en la configuración porque es una regla de la
     * ficha, no una palanca de operación: cambiarla cambia qué significa
     * reprocesar un evento viejo.
     */
    public const RETENTION_DAYS = 180;

    /**
     * Acotado para no bloquear la tabla, y repetido hasta agotar: el tope no
     * es cuántas se borran en total, es cuántas por transacción.
     */
    public const BATCH = 1000;

    /**
     * Un tope de rondas, para que un fallo raro no deje el comando dando
     * vueltas para siempre. Con el lote de arriba son 100.000 filas por
     * ejecución; lo que sobre se borra mañana, que es inocuo.
     */
    private const MAX_BATCHES = 100;

    public function __construct(
        private ProcessedEventRepository $processedEvents,
        private Clock $clock,
    ) {
    }

    /**
     * @return int cuántas filas ha borrado
     */
    public function __invoke(): int
    {
        $cutoff = $this->clock->now()->modify(\sprintf('-%d days', self::RETENTION_DAYS));
        $purged = 0;

        for ($round = 0; $round < self::MAX_BATCHES; ++$round) {
            $deleted = $this->processedEvents->purgeOlderThan($cutoff, self::BATCH);

            if (0 === $deleted) {
                return $purged;
            }

            $purged += $deleted;
        }

        return $purged;
    }
}
