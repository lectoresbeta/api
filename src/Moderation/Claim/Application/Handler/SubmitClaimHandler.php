<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Contract\ClaimableCorrections;
use LectoresBeta\Moderation\Claim\Application\Command\SubmitClaim;
use LectoresBeta\Moderation\Claim\Application\DTO\SubmittedClaim;
use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimReason;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimType;
use LectoresBeta\Moderation\Claim\Domain\Exception\ClaimRefused;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRestrictionRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Presentar una reclamación (`FEAT-MOD-001`).
 *
 * **No produce ningún efecto** (`RN-1`): no oculta nada, no congela nada y no
 * avisa al reclamado. Registra que alguien pide que se mire algo, y ya.
 *
 * ## Por qué hay tantas puertas antes de registrar
 *
 * Reclamar una corrección **devuelve créditos si se estima**, así que el
 * botón es también una forma de no pagar: «la reclamo como que no aporta
 * valor y recupero mis créditos». Si el sistema no distingue una crítica dura
 * de una corrección fraudulenta, los correctores aprenden a escribir elogios
 * — que es exactamente lo contrario del producto.
 *
 * De ahí las cuatro comprobaciones, y cada una tapa una vía distinta:
 *
 * - **una por persona y objeto** (`RN-2`): diez denuncias de alguien sobre el
 *   mismo texto no son diez señales, son la misma repetida;
 * - **solo el autor de la obra reclama una corrección** (`RN-4`);
 * - **solo una corrección que ya se ha podido leer** (`RN-5`), que cierra lo
 *   de reclamar a ciegas lo retenido por descubierto;
 * - **cupo mensual y bloqueo acumulativo** (`RN-6`, `RN-6b`), que es lo que
 *   hace que desestimar una reclamación tenga consecuencias.
 */
final readonly class SubmitClaimHandler
{
    /**
     * `RN-6`. No es una formalidad: sin tope, el botón de reclamar es una
     * forma barata de no pagar ninguna corrección.
     */
    public const MONTHLY_LIMIT = 3;

    public function __construct(
        private ClaimRepository $claims,
        private ClaimRestrictionRepository $restrictions,
        private ClaimableCorrections $corrections,
        private WorkAccessBriefs $works,
        private RegisteredUsers $people,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(SubmitClaim $command): SubmittedClaim
    {
        $reporterId = $this->partyOrRefuse($command->reporterId);
        $targetType = ClaimTargetType::tryFrom($command->targetType)
            ?? throw ClaimRefused::becauseTheTargetIsNotClaimable();
        $reason = ClaimReason::tryFrom($command->reason)
            ?? throw ClaimRefused::becauseThatReasonDoesNotExist($command->reason);

        // La forma del identificador, **antes de tocar la base de datos**.
        // Todos los objetos reclamables se identifican por UUID, y la columna
        // es de ese tipo: sin esta puerta, un `targetId` con una errata llega
        // hasta la consulta y revienta contra PostgreSQL en vez de
        // rechazarse. Responde lo mismo que un objeto inexistente, que es lo
        // correcto — quien escribe mal un identificador no tiene por qué
        // distinguir las dos cosas.
        $this->partyOrRefuse($command->targetId);

        // Y ahora lo que ya existe: un reintento no consume cupo ni vuelve a
        // comprobar nada. Reclamar dos veces suele ser un doble clic.
        $existing = $this->claims->of($reporterId, $targetType, $command->targetId);

        if (null !== $existing) {
            return new SubmittedClaim($existing->id()->value(), $existing->status()->value, true);
        }

        $registeredBy = null === $command->registeredById ? null : $this->partyOrRefuse($command->registeredById);
        $now = $this->clock->now();
        $this->ensureAllowedToClaim($reporterId, $now, null !== $registeredBy);

        $subjectId = $this->subjectOf($targetType, $command->targetId, $reporterId);

        $claim = new Claim(
            ClaimId::generate(),
            self::typeFor($targetType),
            $targetType,
            $command->targetId,
            $reporterId,
            $reason,
            $now,
            $subjectId,
            self::trimmed($command->description),
            null !== $registeredBy,
            $registeredBy,
        );

        $this->session->execute(function () use ($claim): void {
            $this->claims->save($claim);
        });

        return new SubmittedClaim($claim->id()->value(), $claim->status()->value, false);
    }

    /**
     * El cupo y el bloqueo, en ese orden: quien está bloqueado tiene que
     * saberlo aunque además le quede cupo, porque el bloqueo lleva fecha y el
     * cupo no.
     *
     * **Registrada en nombre de otro, el bloqueo no cuenta y el cupo sí**
     * (`FEAT-MOD-005` `RN-15`). Es lo que da sentido a la vía de correo: si
     * el bloqueo se aplicara también aquí, quien lo tiene no podría reclamar
     * por ningún camino y la puerta de atrás no abriría nada. El cupo se
     * mantiene porque la vía es **más lenta, no más barata**.
     */
    private function ensureAllowedToClaim(PartyId $reporterId, \DateTimeImmutable $now, bool $onBehalf = false): void
    {
        $restriction = $this->restrictions->ofUser($reporterId);

        if (!$onBehalf && null !== $restriction && $restriction->isBlockedAt($now)) {
            throw ClaimRefused::untilTheBlockExpires($restriction->blockedUntil() ?? $now);
        }

        if ($this->claims->countBy($reporterId, $now->modify('-1 month')) >= self::MONTHLY_LIMIT) {
            throw ClaimRefused::becauseTheMonthlyLimitIsSpent(self::MONTHLY_LIMIT);
        }
    }

    /**
     * Contra quién va la reclamación, cuando se puede saber sin salir de lo
     * que el objeto ya dice.
     *
     * Aquí es donde se comprueban `RN-4` y `RN-5`, porque las dos preguntas
     * —quién puede reclamar esto y contra quién va— se responden con el mismo
     * dato.
     */
    private function subjectOf(ClaimTargetType $targetType, string $targetId, PartyId $reporterId): ?PartyId
    {
        if (ClaimTargetType::CORRECTION === $targetType) {
            $correction = $this->corrections->ofId($targetId);

            // Una corrección ajena responde lo mismo que una inexistente: que
            // exista es información sobre una obra que no es suya.
            if (null === $correction || $correction->ownerId !== $reporterId->value()) {
                throw ClaimRefused::becauseTheTargetIsNotClaimable();
            }

            if (!$correction->isReadable) {
                throw ClaimRefused::becauseTheCorrectionHasNotBeenRead();
            }

            return self::party($correction->readerId);
        }

        if (ClaimTargetType::WORK === $targetType) {
            $work = $this->works->ofWork($targetId) ?? throw ClaimRefused::becauseTheTargetIsNotClaimable();

            return self::party($work->authorId);
        }

        if (ClaimTargetType::USER === $targetType) {
            $subject = $this->partyOrRefuse($targetId);

            // Nadie se denuncia a sí mismo (`FEAT-COM-035` `RN-3`): gastaría
            // su propio cupo y el tiempo de quien la lee, y no hay desenlace
            // que signifique nada.
            if ($subject->equals($reporterId)) {
                throw ClaimRefused::againstYourself();
            }

            // Y a quien no existe tampoco. Responde lo mismo que un objeto no
            // reclamable, que es lo correcto: decir «esa cuenta no existe»
            // convertiría el formulario de denunciar en un comprobador de
            // quién está en la plataforma.
            if (!$this->people->exists($targetId)) {
                throw ClaimRefused::becauseTheTargetIsNotClaimable();
            }

            return $subject;
        }

        // Capítulos y publicaciones: el objeto existe en otro contexto y
        // todavía no hay contrato que diga de quién es. La reclamación se
        // registra igual —el moderador lo averigua al abrirla— porque no
        // poder denunciar es peor que no saber aún contra quién.
        return null;
    }

    private function partyOrRefuse(string $id): PartyId
    {
        try {
            return PartyId::fromString($id);
        } catch (InvalidValue) {
            throw ClaimRefused::becauseTheTargetIsNotClaimable();
        }
    }

    private static function party(string $id): ?PartyId
    {
        try {
            return PartyId::fromString($id);
        } catch (InvalidValue) {
            return null;
        }
    }

    private static function typeFor(ClaimTargetType $targetType): ClaimType
    {
        return match ($targetType) {
            ClaimTargetType::WORK => ClaimType::INAPPROPRIATE_WORK,
            ClaimTargetType::CHAPTER => ClaimType::INAPPROPRIATE_CHAPTER,
            ClaimTargetType::CORRECTION => ClaimType::FRAUDULENT_FEEDBACK,
            ClaimTargetType::USER => ClaimType::ABUSIVE_USER,
            ClaimTargetType::POST, ClaimTargetType::POST_COMMENT => ClaimType::INAPPROPRIATE_WORK,
        };
    }

    private static function trimmed(?string $description): ?string
    {
        $text = trim((string) $description);

        return '' === $text ? null : mb_substr($text, 0, 4000);
    }
}
