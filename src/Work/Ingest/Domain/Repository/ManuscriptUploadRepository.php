<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Domain\Repository;

use LectoresBeta\Work\Ingest\Domain\Entity\ManuscriptUpload;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ManuscriptUploadId;

interface ManuscriptUploadRepository
{
    public function ofId(ManuscriptUploadId $id): ?ManuscriptUpload;

    public function save(ManuscriptUpload $upload): void;

    public function remove(ManuscriptUpload $upload): void;

    /**
     * Retira las subidas caducadas y dice cuántas.
     *
     * Saltarse una ejecución es inocuo: una subida caducada ya no se puede
     * confirmar —lo decide su fecha, no esta purga—, así que esto solo
     * recupera espacio.
     */
    public function purgeExpired(\DateTimeImmutable $now): int;
}
