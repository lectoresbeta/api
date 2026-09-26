<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Handler;

use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Entity\ClaimRestriction;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRestrictionRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Application\Command\ReviewClaim;
use LectoresBeta\Moderation\Review\Application\DTO\ReviewedClaim;
use LectoresBeta\Moderation\Review\Domain\Entity\ClaimReview;
use LectoresBeta\Moderation\Review\Domain\Enum\ReviewDecision;
use LectoresBeta\Moderation\Review\Domain\Event\ClaimRejected;
use LectoresBeta\Moderation\Review\Domain\Event\ClaimUpheld;
use LectoresBeta\Moderation\Review\Domain\Exception\ClaimReviewRefused;
use LectoresBeta\Moderation\Review\Domain\Repository\ClaimReviewRepository;
use LectoresBeta\Moderation\Review\Domain\ValueObject\ClaimReviewId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Resolver una reclamación (`FEAT-MOD-002`).
 *
 * Es el único sitio de la plataforma donde una decisión humana mueve
 * créditos de una cuenta a otra y retira una obra de la vista, así que
 * conviene ver de un vistazo qué hace y qué **no** hace.
 *
 * ## Lo que hace
 *
 * Toma el expediente y lo cierra en la misma transacción. No hay un paso de
 * «asignármela»: una asignación que se pide y no se usa deja la cola llena de
 * expedientes retenidos por quien ya se fue a otra cosa. Lo que `RN-8`
 * necesita —que dos moderadores no resuelvan la misma— lo da el **bloqueo de
 * la fila** mientras se decide, no un estado intermedio.
 *
 * Desestimar **no es gratis para quien reclamó**: suma un aviso a su
 * restricción, y el bloqueo es acumulativo (`FEAT-MOD-001` `RN-6b`). Sin eso,
 * reclamar en falso sale gratis y el botón de denunciar se convierte en una
 * forma barata de no pagar una corrección.
 *
 * ## Lo que no hace, y es deliberado
 *
 * **No aplica ningún efecto** (`RN-4`). No devuelve créditos, no bloquea
 * obras y no sanciona a nadie: publica qué se ha decidido y cada contexto lo
 * interpreta en su modelo. La tentación contraria —que el backoffice escriba
 * en las tablas de `Credits` y de `Work`— es más rápida y produce un contexto
 * que lo sabe todo sobre todos.
 *
 * Y **publica después de confirmar**, nunca dentro de la transacción: un
 * hecho anunciado desde un estado que luego se deshace es una mentira que ya
 * no se puede retirar.
 */
final readonly class ReviewClaimHandler
{
    public function __construct(
        private ClaimRepository $claims,
        private ClaimReviewRepository $reviews,
        private ClaimRestrictionRepository $restrictions,
        private RecordAuditEntry $audit,
        private EventPublisher $events,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReviewClaim $command): ReviewedClaim
    {
        $decision = ReviewDecision::tryFrom(strtoupper(trim($command->decision)))
            ?? throw ClaimReviewRefused::becauseThatDecisionDoesNotExist($command->decision);

        $motivation = trim($command->motivation);

        if ('' === $motivation) {
            throw ClaimReviewRefused::becauseThereIsNoMotivation();
        }

        $claimId = self::claim($command->claimId);
        $moderatorId = self::party($command->moderatorId);
        $now = $this->clock->now();

        $announcement = $this->session->execute(
            fn (): IntegrationEvent => $this->resolve($claimId, $moderatorId, $decision, $motivation, $now),
        );

        $this->events->publish($announcement);

        // El estado en que queda el expediente es la decisión: no hay
        // ninguno intermedio que sobreviva a esta llamada.
        return new ReviewedClaim($claimId->value(), $decision->value, $decision->value);
    }

    /**
     * Todo lo que cambia, dentro de la misma transacción: el expediente, la
     * decisión escrita, la restricción de quien reclamó y el registro de
     * auditoría. Un registro que se puede perder mientras el efecto se
     * conserva es peor que no tenerlo, porque invita a confiar en él.
     */
    private function resolve(
        ClaimId $claimId,
        PartyId $moderatorId,
        ReviewDecision $decision,
        string $motivation,
        \DateTimeImmutable $now,
    ): IntegrationEvent {
        $claim = $this->claims->lockedById($claimId) ?? throw ClaimReviewRefused::becauseItDoesNotExist();

        if (!$claim->canBeReviewedBy($moderatorId)) {
            throw ClaimReviewRefused::becauseTheModeratorIsAParty();
        }

        if (!$claim->status()->isOpen()) {
            throw ClaimReviewRefused::becauseItIsAlreadyResolved($claim->status()->value);
        }

        $claim->takeUnderReview($now);

        if (ReviewDecision::UPHELD === $decision) {
            $claim->uphold($now);
        } else {
            $claim->reject($now);
            $this->recordAStrikeAgainst($claim->reporterId(), $now);
        }

        $this->claims->save($claim);
        $this->reviews->add(new ClaimReview(
            ClaimReviewId::generate(),
            $claimId,
            $moderatorId,
            $decision,
            $motivation,
            $now,
        ));

        // Con la motivación, que es justo lo que el expediente necesita y lo
        // que ningún evento lleva: material interno, y puede ser duro.
        $this->audit->of(
            $moderatorId,
            'CLAIM_REVIEWED',
            'CLAIM',
            $claimId->value(),
            $motivation,
            [
                'decision' => $decision->value,
                'targetType' => $claim->targetType()->value,
                'targetId' => $claim->targetId(),
            ],
        );

        return self::announcementOf($claim, $decision, $now);
    }

    /**
     * `FEAT-MOD-001` `RN-6b`: primera desestimada, una semana sin poder
     * reclamar; segunda, dos; tercera, tres. La cuenta la lleva la propia
     * restricción.
     */
    private function recordAStrikeAgainst(PartyId $reporterId, \DateTimeImmutable $now): void
    {
        $restriction = $this->restrictions->ofUser($reporterId) ?? new ClaimRestriction($reporterId, $now);
        $restriction->claimDismissed($now);

        $this->restrictions->save($restriction);
    }

    private static function announcementOf(Claim $claim, ReviewDecision $decision, \DateTimeImmutable $now): IntegrationEvent
    {
        if (ReviewDecision::UPHELD === $decision) {
            return new ClaimUpheld(
                EventId::generate(),
                $claim->id()->value(),
                $claim->type()->value,
                $claim->targetType()->value,
                $claim->targetId(),
                $claim->subjectId()?->value(),
                $now,
            );
        }

        return new ClaimRejected(
            EventId::generate(),
            $claim->id()->value(),
            $claim->reporterId()->value(),
            $now,
        );
    }

    /**
     * Un identificador mal formado responde como uno inexistente. No es
     * cortesía: una respuesta distinta convertiría el endpoint en un
     * comprobador de qué identificadores existen.
     */
    private static function claim(string $value): ClaimId
    {
        try {
            return ClaimId::fromString($value);
        } catch (InvalidValue) {
            throw ClaimReviewRefused::becauseItDoesNotExist();
        }
    }

    private static function party(string $value): PartyId
    {
        try {
            return PartyId::fromString($value);
        } catch (InvalidValue) {
            throw ClaimReviewRefused::becauseTheModeratorIsAParty();
        }
    }
}
