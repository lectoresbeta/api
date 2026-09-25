<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Handler;

use LectoresBeta\Reading\AccessInvitation\Application\Command\InviteBetaReader;
use LectoresBeta\Reading\AccessInvitation\Domain\Entity\AccessInvitation;
use LectoresBeta\Reading\AccessInvitation\Domain\Event\BetaReaderInvited;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationNotFound;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationRefused;
use LectoresBeta\Reading\AccessInvitation\Domain\Repository\AccessInvitationRepository;
use LectoresBeta\Reading\AccessInvitation\Domain\ValueObject\AccessInvitationId;
use LectoresBeta\Reading\AccessRequest\Domain\Repository\AccessRequestRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * El autor elige a quién quiere dentro (`FEAT-RDG-004`).
 *
 * Es el único camino de entrada a una obra `PRIVATE` y el único de los tres
 * que empieza por el autor. Una invitación **no concede nada**: es una
 * oferta, y quien decide es quien la recibe.
 *
 * **Se puede invitar en cualquier modalidad, incluida `PUBLIC`**, al revés
 * que solicitar (`RN-3`). La asimetría parece incoherente hasta que se mira
 * quién trabaja: una solicitud sobre una obra abierta le pide al autor que
 * haga algo por alguien que ya podía entrar solo, y una invitación es el
 * autor decidiendo hacerlo. Además dice algo que la modalidad no dice:
 * *quiero que tú la leas*.
 *
 * **Y se puede invitar a un borrador** (`RN-12`), que es probablemente el
 * caso más valioso: la primera persona a la que un autor enseña algo suele
 * verlo antes de que exista para nadie más. No contradice «el borrador ajeno
 * no existe», porque aquí es el autor quien abre la puerta, a una persona y
 * por su nombre. Lo que el invitado no puede hacer es leerlo antes de que se
 * publique: acepta, y espera.
 */
final readonly class InviteBetaReaderHandler
{
    public function __construct(
        private WorkAccessBriefs $works,
        private RegisteredUsers $users,
        private ReaderMaturity $maturity,
        private AccessInvitationRepository $invitations,
        private AccessRequestRepository $requests,
        private BetaReaderAccessRepository $accesses,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(InviteBetaReader $command): AccessInvitation
    {
        $work = $this->works->ofWork($command->workId);

        // La obra de otra persona responde igual que una inexistente. Aquí no
        // se mira `visibleToOthers`: el autor invita a sus borradores.
        if (null === $work || $work->authorId !== $command->authorId) {
            throw InvitationNotFound::work();
        }

        $inviteeId = $this->invitee($command->inviteeId, $command->authorId);

        // La edad se comprueba **al invitar** y no solo al leer
        // (`FEAT-USR-044` `U-22`). Antes la invitación se cursaba y la
        // lectura fallaba después: el autor veía a alguien aceptar y no poder
        // entrar, sin ninguna explicación.
        if ($work->adultsOnly && !$this->maturity->isOfAge($inviteeId->value())) {
            throw InvitationRefused::readerCannotSeeThisWork();
        }

        $workId = WorkId::fromString($command->workId);

        if (null !== $this->accesses->liveFor($inviteeId, $workId)) {
            throw InvitationRefused::alreadyABetaReader();
        }

        // Si ya lo pidió, lo que toca es aceptarlo: dos objetos que
        // significan lo mismo son dos sitios donde el acceso puede nacer.
        if (null !== $this->requests->openOf($inviteeId, $workId)) {
            throw InvitationRefused::requestAlreadyPending();
        }

        if (null !== $this->invitations->openFor($inviteeId, $workId)) {
            throw InvitationRefused::alreadyPending();
        }

        $now = $this->clock->now();
        $invitation = new AccessInvitation(
            AccessInvitationId::generate(),
            $workId,
            AuthorId::fromString($command->authorId),
            $inviteeId,
            $now,
            self::note($command->message),
        );

        $this->session->execute(function () use ($invitation): void {
            $this->invitations->save($invitation);
        });

        $this->events->publish(new BetaReaderInvited(
            EventId::generate(),
            $invitation->id(),
            $workId,
            AuthorId::fromString($command->authorId),
            $inviteeId,
            $now,
        ));

        return $invitation;
    }

    /**
     * A quién se invita. **Se comprueba que existe**, porque invitar a un
     * identificador inventado crearía una invitación que nadie puede aceptar
     * y un aviso que no se puede entregar.
     *
     * De dónde sale ese identificador es otra funcionalidad —buscar lectores
     * beta, `FEAT-RDG-006`— que todavía no existe; mientras tanto se llega a
     * una persona por su perfil público.
     */
    private function invitee(?string $inviteeId, string $authorId): ReaderId
    {
        if (null === $inviteeId || !$this->users->exists($inviteeId)) {
            throw InvitationRefused::userNotFound();
        }

        if ($inviteeId === $authorId) {
            throw InvitationRefused::authorCannotBeInvited();
        }

        try {
            return ReaderId::fromString($inviteeId);
        } catch (InvalidValue) {
            throw InvitationRefused::userNotFound();
        }
    }

    private static function note(?string $message): ?string
    {
        if (null === $message) {
            return null;
        }

        $trimmed = trim($message);

        return '' === $trimmed ? null : $trimmed;
    }
}
