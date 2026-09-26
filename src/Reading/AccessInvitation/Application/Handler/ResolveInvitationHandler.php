<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Handler;

use LectoresBeta\Reading\AccessInvitation\Application\Command\ResolveInvitation;
use LectoresBeta\Reading\AccessInvitation\Domain\Entity\AccessInvitation;
use LectoresBeta\Reading\AccessInvitation\Domain\Enum\InvitationDecision;
use LectoresBeta\Reading\AccessInvitation\Domain\Event\BetaReaderInvitationDeclined;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationNotFound;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationRefused;
use LectoresBeta\Reading\AccessInvitation\Domain\Repository\AccessInvitationRepository;
use LectoresBeta\Reading\AccessInvitation\Domain\ValueObject\AccessInvitationId;
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
 * Quien recibe la oferta decide (`FEAT-RDG-005`).
 *
 * La gemela exacta de resolver una solicitud, con los papeles cambiados:
 * allí el autor contesta lo que pidió el lector, aquí el lector contesta lo
 * que ofreció el autor. Aceptar **cierra la invitación y concede el acceso en
 * la misma transacción**, porque los dos agregados son de este contexto.
 *
 * **Aceptar no comprueba la modalidad ni el estado de la obra** (`RN-8`): la
 * modalidad gobierna quién puede entrar, no quién ya fue invitado a entrar.
 * Si pasó a `PUBLIC` el acceso ya no añade permiso, solo procedencia; si se
 * cerró a corrección, se puede leer igual; y si sigue siendo un borrador, se
 * acepta y se espera a que se publique.
 *
 * Rechazar **sí se cuenta al autor**, y cancelar una solicitud no. No es
 * incoherente: quien cancela retira una pregunta suya y nadie esperaba; quien
 * rechaza contesta a alguien que sí.
 */
final readonly class ResolveInvitationHandler
{
    public function __construct(
        private AccessInvitationRepository $invitations,
        private BetaReaderAccessRepository $accesses,
        private WorkAccessBriefs $works,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ResolveInvitation $command): AccessInvitation
    {
        $decision = InvitationDecision::tryFrom((string) $command->decision);

        if (null === $decision) {
            throw InvitationRefused::unknownDecision();
        }

        $invitation = $this->mine($command);

        if (!$invitation->isOpen()) {
            throw InvitationRefused::alreadyResolved();
        }

        // `410` y no `404`: le invitaron a esa obra, así que existió.
        if (null === $this->works->ofWork($invitation->workId()->value())) {
            throw InvitationRefused::workIsGone();
        }

        $now = $this->clock->now();

        if (InvitationDecision::DECLINED === $decision) {
            $this->session->execute(function () use ($invitation, $now): void {
                $invitation->decline($now);
                $this->invitations->save($invitation);
            });

            $this->events->publish(new BetaReaderInvitationDeclined(
                EventId::generate(),
                $invitation->id(),
                $invitation->workId(),
                $invitation->authorId(),
                $invitation->inviteeId(),
                $now,
            ));

            return $invitation;
        }

        $this->events->publish(...$this->accept($invitation, $now));

        return $invitation;
    }

    /**
     * Aceptar, contemplando que el invitado **ya** tenga acceso por otro
     * camino: la invitación se cierra igual y no nace un segundo acceso.
     *
     * @return list<IntegrationEvent>
     */
    private function accept(AccessInvitation $invitation, \DateTimeImmutable $now): array
    {
        $existing = $this->accesses->liveFor($invitation->inviteeId(), $invitation->workId());

        $access = $existing ?? new BetaReaderAccess(
            BetaReaderAccessId::generate(),
            $invitation->workId(),
            $invitation->inviteeId(),
            $invitation->authorId(),
            AccessSource::AUTHOR_INVITATION,
            $now,
        );

        $this->session->execute(function () use ($invitation, $access, $existing, $now): void {
            $invitation->accept($now);
            $this->invitations->save($invitation);

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
            $invitation->workId(),
            $invitation->authorId(),
            $invitation->inviteeId(),
            AccessSource::AUTHOR_INVITATION->value,
            $now,
        )];
    }

    private function mine(ResolveInvitation $command): AccessInvitation
    {
        try {
            $invitation = $this->invitations->ofId(AccessInvitationId::fromString($command->invitationId));
        } catch (InvalidValue) {
            throw InvitationNotFound::invitation();
        }

        // Ni siquiera el autor que la envió la resuelve: la retira.
        if (null === $invitation || $invitation->inviteeId()->value() !== $command->inviteeId) {
            throw InvitationNotFound::invitation();
        }

        return $invitation;
    }
}
