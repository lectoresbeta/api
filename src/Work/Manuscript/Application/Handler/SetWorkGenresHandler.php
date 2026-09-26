<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Command\SetWorkGenres;
use LectoresBeta\Work\Manuscript\Application\Service\DeclareGenres;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Reclasificar una obra (`FEAT-WRK-001`).
 *
 * Se puede en cualquier momento y sin consecuencias: la temática no cuesta
 * créditos, no cambia lo que ya se corrigió y lo único que mueve es dónde
 * aparece la obra cuando alguien filtra el catálogo.
 *
 * No publica ningún evento. Nadie fuera de este contexto usa todavía las
 * temáticas de una obra, y publicar un hecho que nadie escucha es inventarse
 * un contrato que luego hay que mantener.
 */
final readonly class SetWorkGenresHandler
{
    public function __construct(
        private WorkRepository $works,
        private DeclareGenres $genres,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(SetWorkGenres $command): void
    {
        $work = $this->works->ofId(WorkId::fromString($command->workId));

        // Ajena y no existente son la misma respuesta: confirmar que una obra
        // existe ya es decir algo de ella.
        if (null === $work || !$work->authorId()->equals(AuthorId::fromString($command->authorId))) {
            throw WorkNotFound::create();
        }

        $this->session->execute(function () use ($work, $command): void {
            $this->genres->on($work->id(), $command->genres);
        });
    }
}
