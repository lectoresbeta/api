<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Handler;

use LectoresBeta\Reading\AccessInvitation\Application\DTO\InvitableReader;
use LectoresBeta\Reading\AccessInvitation\Application\Query\SearchInvitableReaders;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationNotFound;
use LectoresBeta\Reading\AccessInvitation\Domain\Repository\AccessInvitationRepository;
use LectoresBeta\Reading\AccessRequest\Domain\Repository\AccessRequestRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use LectoresBeta\User\Account\Application\Contract\ReaderDirectory;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * A quién puede invitar todavía el autor (`FEAT-RDG-006`).
 *
 * **Esto no es un buscador de personas.** El índice es de `User`, que posee
 * los perfiles y los nombres; lo que aporta este caso de uso es el descarte,
 * que es lo único que `Reading` sabe: quitar de la lista a quien ya está
 * dentro de esa obra.
 *
 * Que la búsqueda cuelgue de una obra no es decorativo. Liga recorrer
 * personas a **un autor con una obra**, que es la única razón legítima para
 * hacerlo aquí, y evita que esto se convierta en un directorio general por la
 * puerta de atrás.
 *
 * Se piden más candidatos de los que se devuelven porque el descarte ocurre
 * después: pedir diez y quitar tres dejaría siete donde había diez.
 */
final readonly class SearchInvitableReadersHandler
{
    /** Un desplegable de autocompletado, no una lista que alguien recorre. */
    public const LIMIT = 10;

    /**
     * El margen con el que se pide, para que descartar no deje la lista
     * corta. Tres veces el tope cubre el caso real —un autor con unos cuantos
     * lectores ya dentro— sin traerse media tabla por si acaso.
     */
    private const CANDIDATES = 3 * self::LIMIT;

    public function __construct(
        private WorkAccessBriefs $works,
        private ReaderDirectory $directory,
        private AccessInvitationRepository $invitations,
        private AccessRequestRepository $requests,
        private BetaReaderAccessRepository $accesses,
    ) {
    }

    /**
     * @return list<InvitableReader>
     */
    public function __invoke(SearchInvitableReaders $query): array
    {
        $work = $this->works->ofWork($query->workId);

        if (null === $work || $work->authorId !== $query->authorId) {
            throw InvitationNotFound::work();
        }

        $workId = WorkId::fromString($query->workId);
        $invitable = [];

        foreach ($this->directory->search((string) $query->query, self::CANDIDATES) as $candidate) {
            if (self::LIMIT === \count($invitable)) {
                break;
            }

            if ($candidate->userId === $work->authorId || !$this->isInvitable($candidate, $workId)) {
                continue;
            }

            $invitable[] = new InvitableReader(
                $candidate->userId,
                $candidate->username,
                $candidate->name,
                $candidate->avatarUrl,
            );
        }

        return $invitable;
    }

    /**
     * Las tres razones por las que alguien ya no se puede invitar, y son las
     * mismas tres que `InviteBetaReaderHandler` rechaza: enseñarle a alguien
     * en la lista para después negarle la invitación sería peor que no
     * enseñarlo.
     */
    private function isInvitable(DirectoryEntry $candidate, WorkId $workId): bool
    {
        try {
            $readerId = ReaderId::fromString($candidate->userId);
        } catch (InvalidValue) {
            return false;
        }

        return null === $this->accesses->liveFor($readerId, $workId)
            && null === $this->invitations->openFor($readerId, $workId)
            && null === $this->requests->openOf($readerId, $workId);
    }
}
