<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Contract\DeliveredCorrectionCount;
use LectoresBeta\Moderation\Administration\Application\DTO\AdminClaimRow;
use LectoresBeta\Moderation\Administration\Application\DTO\AdminSanctionRow;
use LectoresBeta\Moderation\Administration\Application\DTO\AdminUserSheet;
use LectoresBeta\Moderation\Administration\Application\Query\GetUserSheet;
use LectoresBeta\Moderation\Administration\Domain\Exception\AdministrationRefused;
use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Sanction\Domain\Entity\Sanction;
use LectoresBeta\Moderation\Sanction\Domain\Repository\SanctionRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\AdministrableAccounts;
use LectoresBeta\Work\Manuscript\Application\Contract\AuthoredWorkCount;

/**
 * La ficha de un usuario (`FEAT-MOD-005`).
 *
 * **Compone, no posee.** La identidad y el estado los contesta `User` por su
 * contrato, los relatos `Work` y las correcciones `Feedback`; lo único que
 * este contexto saca de su propia casa son las reclamaciones y las sanciones.
 *
 * **Un contador que no se puede leer no tumba la ficha**, igual que en el
 * perfil: viaja nulo y el moderador ve el resto. Quien atiende a una persona
 * necesita la pantalla abierta más de lo que necesita esa cifra.
 *
 * Y se audita, porque consultarla es un acto: `RN-1` no distingue entre mirar
 * y cambiar.
 */
final readonly class GetUserSheetHandler
{
    public function __construct(
        private AdministrableAccounts $accounts,
        private ClaimRepository $claims,
        private SanctionRepository $sanctions,
        private AuthoredWorkCount $works,
        private DeliveredCorrectionCount $corrections,
        private RecordAuditEntry $audit,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(GetUserSheet $query): AdminUserSheet
    {
        $account = $this->accounts->ofId($query->userId);

        if (null === $account) {
            throw AdministrationRefused::userNotFound();
        }

        try {
            $party = PartyId::fromString($account->userId);
        } catch (InvalidValue) {
            throw AdministrationRefused::userNotFound();
        }

        $now = $this->clock->now();

        $sheet = new AdminUserSheet(
            $account,
            $this->attempt(fn (): int => $this->works->ofAuthor($account->userId)),
            $this->attempt(fn (): int => $this->corrections->ofReader($account->userId)),
            array_map(self::asClaimRow(...), $this->claims->by($party)),
            array_map(self::asClaimRow(...), $this->claims->about($party)),
            array_map(
                static fn (Sanction $sanction): AdminSanctionRow => self::asSanctionRow($sanction, $now),
                $this->sanctions->historyOf($party),
            ),
        );

        $this->session->execute(function () use ($query, $account): void {
            $this->audit->of(
                PartyId::fromString($query->moderatorId),
                'USER_SHEET_READ',
                'USER',
                $account->userId,
            );
        });

        return $sheet;
    }

    private static function asClaimRow(Claim $claim): AdminClaimRow
    {
        return new AdminClaimRow(
            $claim->id()->value(),
            $claim->type()->value,
            $claim->status()->value,
            $claim->reason()->value,
            $claim->submittedAt()->format(\DATE_ATOM),
            $claim->isFiledOnBehalf(),
        );
    }

    private static function asSanctionRow(Sanction $sanction, \DateTimeImmutable $now): AdminSanctionRow
    {
        return new AdminSanctionRow(
            $sanction->id()->value(),
            $sanction->type()->value,
            $sanction->reason(),
            $sanction->imposedAt()->format(\DATE_ATOM),
            $sanction->expiresAt()?->format(\DATE_ATOM),
            $sanction->liftedAt()?->format(\DATE_ATOM),
            $sanction->isInForceAt($now),
        );
    }

    /**
     * @param \Closure(): int $ask
     */
    private function attempt(\Closure $ask): ?int
    {
        try {
            return $ask();
        } catch (\Throwable) {
            return null;
        }
    }
}
