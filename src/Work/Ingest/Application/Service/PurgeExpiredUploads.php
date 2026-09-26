<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Ingest\Domain\Repository\ManuscriptUploadRepository;

/**
 * Retirar los manuscritos que nadie confirmó (`FEAT-WRK-002`).
 *
 * **Saltarse una ejecución es inocuo**, igual que en la purga de alias
 * (`FEAT-USR-036`): una subida caducada ya no se puede confirmar —lo decide
 * su fecha, no esta purga—, así que esto solo recupera espacio.
 *
 * Y espacio del que importa: cada fila abandonada es una novela entera. Es la
 * contrapartida de que el troceado se proponga en vez de decidirse.
 */
final readonly class PurgeExpiredUploads
{
    public function __construct(
        private ManuscriptUploadRepository $uploads,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(): int
    {
        return $this->session->execute(fn (): int => $this->uploads->purgeExpired($this->clock->now()));
    }
}
