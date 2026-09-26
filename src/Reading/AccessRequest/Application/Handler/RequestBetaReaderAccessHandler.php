<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Handler;

use LectoresBeta\Reading\AccessRequest\Application\Command\RequestBetaReaderAccess;
use LectoresBeta\Reading\AccessRequest\Application\Service\VisibleWork;
use LectoresBeta\Reading\AccessRequest\Domain\Entity\AccessRequest;
use LectoresBeta\Reading\AccessRequest\Domain\Event\AccessRequested;
use LectoresBeta\Reading\AccessRequest\Domain\Exception\AccessRequestRefused;
use LectoresBeta\Reading\AccessRequest\Domain\Repository\AccessRequestRepository;
use LectoresBeta\Reading\AccessRequest\Domain\ValueObject\AccessRequestId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Enum\WorkAccessMode;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Pedir permiso para leer una obra (`FEAT-RDG-002`).
 *
 * Es el camino intermedio de los tres, y el que desbloquea la modalidad por
 * defecto del producto: una obra nace `ON_REQUEST`, así que hasta que esto
 * existió, **publicar una obra no la abría a nadie**.
 *
 * Una solicitud no concede nada. Lo único que produce por sí sola es un aviso
 * al autor, que es quien decide en
 * [`FEAT-RDG-003`](../../../../../docs/features/reading/FEAT-RDG-003-resolve-access-request.md).
 *
 * Las comprobaciones van de la más callada a la más explícita: primero lo que
 * no se puede ni confirmar que existe, después lo que se nombra. El orden es
 * la regla, no un detalle de escritura.
 */
final readonly class RequestBetaReaderAccessHandler
{
    public function __construct(
        private VisibleWork $works,
        private AccessRequestRepository $requests,
        private BetaReaderAccessRepository $accesses,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RequestBetaReaderAccess $command): AccessRequest
    {
        $work = $this->works->to($command->workId, $command->requesterId);

        if ($work->authorId === $command->requesterId) {
            throw AccessRequestRefused::authorCannotAskForAccess();
        }

        // La modalidad llega como cadena y se traduce al vocabulario de este
        // contexto: qué significa cada una **para entrar** es regla de aquí,
        // y una regla no se escribe sobre el enum de otro contexto.
        if (true !== WorkAccessMode::tryFrom($work->accessMode)?->takesRequests()) {
            throw AccessRequestRefused::workDoesNotTakeRequests();
        }

        $workId = WorkId::fromString($command->workId);
        $readerId = ReaderId::fromString($command->requesterId);

        if (null !== $this->accesses->liveFor($readerId, $workId)) {
            throw AccessRequestRefused::alreadyABetaReader();
        }

        if (null !== $this->requests->openOf($readerId, $workId)) {
            throw AccessRequestRefused::alreadyPending();
        }

        $now = $this->clock->now();
        $request = new AccessRequest(
            AccessRequestId::generate(),
            $workId,
            $readerId,
            AuthorId::fromString($work->authorId),
            $now,
            self::note($command->message),
        );

        $this->session->execute(function () use ($request): void {
            $this->requests->save($request);
        });

        $this->events->publish(new AccessRequested(
            EventId::generate(),
            $request->id(),
            $workId,
            AuthorId::fromString($work->authorId),
            $readerId,
            $now,
        ));

        return $request;
    }

    /**
     * Un mensaje en blanco no es un mensaje. Guardarlo como cadena vacía
     * haría que la pantalla del autor enseñase una comilla vacía donde no hay
     * nada que leer.
     */
    private static function note(?string $message): ?string
    {
        if (null === $message) {
            return null;
        }

        $trimmed = trim($message);

        return '' === $trimmed ? null : $trimmed;
    }
}
