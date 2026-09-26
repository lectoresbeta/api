<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Handler;

use LectoresBeta\Reading\AccessRequest\Application\Command\ResolveAccessRequest;
use LectoresBeta\Reading\AccessRequest\Domain\Entity\AccessRequest;
use LectoresBeta\Reading\AccessRequest\Domain\Enum\RequestDecision;
use LectoresBeta\Reading\AccessRequest\Domain\Event\AccessRequestRejected;
use LectoresBeta\Reading\AccessRequest\Domain\Exception\AccessRequestNotFound;
use LectoresBeta\Reading\AccessRequest\Domain\Exception\AccessRequestRefused;
use LectoresBeta\Reading\AccessRequest\Domain\Repository\AccessRequestRepository;
use LectoresBeta\Reading\AccessRequest\Domain\ValueObject\AccessRequestId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Entity\BetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Enum\AccessSource;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Event\BetaReaderAccessGranted;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\BetaReaderAccessId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * El autor decide quién lee su obra (`FEAT-RDG-003`).
 *
 * **Aceptar cierra la solicitud y concede el acceso en la misma
 * transacción.** Va contra la costumbre del proyecto —un hecho, una cola, un
 * consumidor— y es correcto porque aquí no hay frontera que cruzar: los dos
 * agregados son de `Reading`, y meter una cola entre dos tablas del mismo
 * contexto solo abriría una ventana en la que la solicitud está aceptada y el
 * acceso todavía no existe.
 *
 * **No se comprueba la modalidad de la obra** (`RN-9`). Entre la solicitud y
 * su respuesta el autor pudo cambiarla, y aceptar igual es lo correcto por el
 * mismo motivo que `RN-3` de `FEAT-WRK-007`: la modalidad gobierna quién
 * puede entrar a partir de ahora, no quién ya estaba pidiendo entrar. Si pasó
 * a `PRIVATE`, el autor tiene la solicitud delante y puede rechazarla.
 */
final readonly class ResolveAccessRequestHandler
{
    public function __construct(
        private AccessRequestRepository $requests,
        private BetaReaderAccessRepository $accesses,
        private WorkAccessBriefs $works,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ResolveAccessRequest $command): AccessRequest
    {
        $decision = RequestDecision::tryFrom((string) $command->decision);

        if (null === $decision) {
            throw AccessRequestRefused::unknownDecision();
        }

        $request = $this->mine($command);

        if (!$request->isOpen()) {
            throw AccessRequestRefused::alreadyResolved();
        }

        // `410` y no `404`: quien resuelve conoce esa obra, es suya. Fingir
        // que nunca existió le confundiría sobre algo que hizo él.
        if (null === $this->works->ofWork($request->workId()->value())) {
            throw AccessRequestRefused::workIsGone();
        }

        $now = $this->clock->now();

        if (RequestDecision::REJECTED === $decision) {
            $this->session->execute(function () use ($request, $now): void {
                $request->reject($now);
                $this->requests->save($request);
            });

            $this->events->publish(new AccessRequestRejected(
                EventId::generate(),
                $request->id(),
                $request->workId(),
                $request->requesterId(),
                $now,
            ));

            return $request;
        }

        $this->events->publish(...$this->accept($request, $now));

        return $request;
    }

    /**
     * Aceptar, con el caso que hace falta contemplar: que el lector **ya**
     * tenga acceso por otro camino. La solicitud se cierra igual y no se crea
     * un segundo acceso — la invariante de uno vivo por par manda sobre la
     * operación, y el índice único la haría cumplir de todas formas.
     *
     * @return list<IntegrationEvent>
     */
    private function accept(AccessRequest $request, \DateTimeImmutable $now): array
    {
        $existing = $this->accesses->liveFor($request->requesterId(), $request->workId());

        $access = $existing ?? new BetaReaderAccess(
            BetaReaderAccessId::generate(),
            $request->workId(),
            $request->requesterId(),
            $request->authorId(),
            AccessSource::REQUEST_APPROVED,
            $now,
        );

        $this->session->execute(function () use ($request, $access, $existing, $now): void {
            $request->accept($now);
            $this->requests->save($request);

            if (null === $existing) {
                $this->accesses->save($access);
            }
        });

        if (null !== $existing) {
            return [];
        }

        return [new BetaReaderAccessGranted(
            EventId::generate(),
            $access->id(),
            $request->workId(),
            $request->authorId(),
            $request->requesterId(),
            AccessSource::REQUEST_APPROVED->value,
            $now,
        )];
    }

    private function mine(ResolveAccessRequest $command): AccessRequest
    {
        try {
            $request = $this->requests->ofId(AccessRequestId::fromString($command->requestId));
        } catch (InvalidValue) {
            throw AccessRequestNotFound::request();
        }

        // La solicitud de la obra de otra persona no existe para quien
        // pregunta, igual que una inventada.
        if (null === $request || $request->authorId()->value() !== $command->authorId) {
            throw AccessRequestNotFound::request();
        }

        return $request;
    }
}
