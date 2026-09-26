<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Handler;

use LectoresBeta\Reading\AccessRequest\Application\Command\CancelAccessRequest;
use LectoresBeta\Reading\AccessRequest\Domain\Exception\AccessRequestNotFound;
use LectoresBeta\Reading\AccessRequest\Domain\Exception\AccessRequestRefused;
use LectoresBeta\Reading\AccessRequest\Domain\Repository\AccessRequestRepository;
use LectoresBeta\Reading\AccessRequest\Domain\ValueObject\AccessRequestId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Retirarse de la cola (`FEAT-RDG-002` `RN-7`).
 *
 * **No publica nada**, y es la asimetría deliberada con rechazar: quien
 * cancela retira una pregunta que hizo él y nadie estaba esperando. Quien
 * rechaza contesta a alguien que sí esperaba.
 *
 * Cancelar deja la vía libre para volver a pedir, porque el índice único solo
 * cubre las solicitudes `PENDING`.
 */
final readonly class CancelAccessRequestHandler
{
    public function __construct(
        private AccessRequestRepository $requests,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CancelAccessRequest $command): void
    {
        try {
            $request = $this->requests->ofId(AccessRequestId::fromString($command->requestId));
        } catch (InvalidValue) {
            throw AccessRequestNotFound::request();
        }

        // Ajena y no existente son la misma respuesta: confirmar que una
        // solicitud existe ya es decir algo de quién quiere leer qué.
        if (null === $request || $request->requesterId()->value() !== $command->requesterId) {
            throw AccessRequestNotFound::request();
        }

        if (!$request->isOpen()) {
            throw AccessRequestRefused::alreadyResolved();
        }

        $this->session->execute(function () use ($request): void {
            $request->cancel($this->clock->now());
            $this->requests->save($request);
        });
    }
}
