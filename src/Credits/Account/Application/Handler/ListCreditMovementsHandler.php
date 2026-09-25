<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Handler;

use LectoresBeta\Credits\Account\Application\DTO\CreditMovement;
use LectoresBeta\Credits\Account\Application\Query\ListCreditMovements;
use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El historial de movimientos (`FEAT-CRD-008`).
 *
 * **Es la invariante del contexto, enseñada.** El saldo es la suma de sus
 * movimientos ([`decision:0006`](../../../../../docs/decisions/0006-credit-system.md)
 * `RN-1`), y esta pantalla es lo que permite a una persona comprobarlo en vez
 * de creérselo.
 *
 * Hace falta ahora y no antes: desde [`FEAT-MOD-002`](../../../../../docs/features/moderation/FEAT-MOD-002-review-claim.md)
 * una reclamación estimada puede **retirar créditos ya cobrados**, días
 * después y sin que su dueño haya hecho nada. Un saldo que baja once créditos
 * sin explicación es la clase de opacidad que hace que la gente deje de
 * confiar en la moneda.
 *
 * `balanceAfter` se calcula **acumulando hacia atrás desde el saldo actual**
 * y no se guarda en cada fila: un dato derivado almacenado puede contradecir
 * a la suma, que es justo el fallo que `RN-11` existe para detectar.
 */
final readonly class ListCreditMovementsHandler
{
    private const MAX_PAGE = 100;

    public function __construct(
        private CreditTransactionRepository $transactions,
        private CreditAccountRepository $accounts,
    ) {
    }

    /**
     * @return list<CreditMovement>
     */
    public function __invoke(ListCreditMovements $query): array
    {
        try {
            $userId = UserId::fromString($query->userId);
        } catch (InvalidValue) {
            return [];
        }

        $limit = max(1, min(self::MAX_PAGE, $query->limit));
        $offset = max(0, $query->offset);

        $movements = $this->transactions->historyOf(
            $userId,
            $limit,
            $offset,
            null === $query->reason ? null : CreditTransactionReason::tryFrom(strtoupper($query->reason)),
            self::moment($query->from),
            self::moment($query->to),
        );

        // Consultar no crea la cuenta de quien todavía no tiene ninguna
        // (`RN-9`): saldo cero y lista vacía.
        $balance = $this->accounts->ofUser($userId)?->balance() ?? 0;

        // Hacia atrás desde el saldo de ahora: el primer apunte de la página
        // dejó el saldo en lo que hay hoy **menos todo lo que se movió
        // después**. Una sola consulta, y no una por fila.
        if ([] !== $movements) {
            $balance -= $this->transactions->sumAfter($userId, $movements[0]);
        }

        $seen = [];

        foreach ($movements as $movement) {
            $seen[] = self::narrate($movement, $balance);
            $balance -= $movement->amount();
        }

        return $seen;
    }

    private static function narrate(CreditTransaction $movement, int $balanceAfter): CreditMovement
    {
        $metadata = $movement->metadata();

        return new CreditMovement(
            $movement->id()->value(),
            $movement->amount(),
            $movement->reason()->value,
            $balanceAfter,
            $movement->occurredAt()->format(\DATE_ATOM),
            self::text($metadata['correctionId'] ?? null),
            self::text($metadata['chapterId'] ?? null),
            self::text($metadata['workId'] ?? null),
            self::text($metadata['claimId'] ?? null),
            true === ($metadata['reconstructedPrice'] ?? false),
        );
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private static function moment(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
