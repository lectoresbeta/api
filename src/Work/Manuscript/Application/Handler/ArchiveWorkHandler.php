<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Command\ArchiveWork;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkArchived;
use LectoresBeta\Work\Manuscript\Domain\Exception\ConfirmationRequired;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkIsBlocked;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Retirar una obra (`FEAT-WRK-006`).
 *
 * El botón dice «Eliminar» y lo que ocurre es un **archivado**: desaparece
 * del catálogo, del perfil y de las búsquedas, y solo la ve su autor.
 *
 * Borrar no es una operación local. De una obra de esta plataforma cuelgan
 * correcciones **pagadas**, reclamaciones resueltas, accesos concedidos y
 * movimientos de crédito que la citan; borrarla destruiría el trabajo de
 * otras personas y la prueba de lo que se decidió. Archivar deja fuera a
 * todos menos al autor —que es lo que el autor realmente pide— y conserva lo
 * que no es suyo.
 *
 * Una obra **bloqueada por moderación no se archiva** (`RN-8`): sería la vía
 * para hacer desaparecer contenido reclamado.
 */
final readonly class ArchiveWorkHandler
{
    public function __construct(
        private WorkRepository $works,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ArchiveWork $command): void
    {
        $work = $this->owned($command->workId, $command->authorId);

        if ($work->isArchived()) {
            return;
        }

        // La confirmación la comprueba el servidor y no solo la pantalla.
        if (!$command->confirmed) {
            throw ConfirmationRequired::create();
        }

        if ($work->isBlocked()) {
            throw WorkIsBlocked::create();
        }

        $now = $this->clock->now();
        $work->archive($now);

        $this->session->execute(function () use ($work): void {
            $this->works->save($work);
        });

        $this->events->publish(new WorkArchived(
            EventId::generate(),
            $work->id(),
            $work->authorId(),
            $now,
        ));
    }

    private function owned(string $workId, string $authorId): Work
    {
        try {
            $work = $this->works->ofId(WorkId::fromString($workId));
            $author = AuthorId::fromString($authorId);
        } catch (InvalidValue) {
            throw WorkNotFound::create();
        }

        if (null === $work || !$work->authorId()->equals($author)) {
            throw WorkNotFound::create();
        }

        return $work;
    }
}
