<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Monitoring\Application\Handler;

use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Monitoring\Application\DTO\EconomyHealth;
use LectoresBeta\Credits\Monitoring\Application\Query\GetEconomyHealth;
use LectoresBeta\Credits\Monitoring\Domain\Service\AccountingInvariant;
use LectoresBeta\Credits\Overdraft\Domain\Repository\OverdraftGrantRepository;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;

/**
 * El estado de la economía, en una respuesta (`FEAT-CRD-012`).
 *
 * **Todas las cifras salen del registro de movimientos y de los saldos**
 * (`RN-1`). No hay contadores paralelos que mantener, y por tanto no hay
 * contadores paralelos que puedan desviarse — que es exactamente el fallo que
 * la invariante existe para detectar.
 *
 * **Nada identifica a nadie** (`RN-2`): son cifras agregadas. El saldo más
 * bajo del sistema es un número, no una persona.
 *
 * Los umbrales viven en configuración y no en el código porque son lo que se
 * va a mover en cuanto haya datos reales (`C-34`), y mover un umbral no
 * debería ser un despliegue. Sin umbral, una métrica es decorativa: dice una
 * cifra y no dice si está bien.
 */
final readonly class GetEconomyHealthHandler
{
    public function __construct(
        private AccountingInvariant $invariant,
        private CreditAccountRepository $accounts,
        private CreditTransactionRepository $transactions,
        private OverdraftGrantRepository $overdrafts,
        private ChapterPriceRepository $prices,
        private int $debtAlertThreshold,
        private float $zeroBalanceAlertShare,
        private float $overdraftRecoveryAlertRate,
    ) {
    }

    public function __invoke(GetEconomyHealth $query): EconomyHealth
    {
        $from = self::moment($query->from);
        $to = self::moment($query->to);

        $check = $this->invariant->check();
        $accounts = $this->accounts->balanceSpread();
        $overdraft = $this->overdrafts->recovery();

        $health = new EconomyHealth(
            $check,
            $from?->format(\DATE_ATOM),
            $to?->format(\DATE_ATOM),
            $accounts,
            $overdraft,
            $this->transactions->tallyOfReason(CreditTransactionReason::MANUAL_ADJUSTMENT, $from, $to),
            $this->prices->countCorrectable(),
            [],
        );

        return $health->withAlerts($this->alerts($health));
    }

    /**
     * @return list<string>
     */
    private function alerts(EconomyHealth $health): array
    {
        $alerts = [];

        // La primera y la única que no admite discusión: si falla, alguien
        // tiene créditos que nadie pagó.
        if (!$health->invariant->holds()) {
            $alerts[] = 'ACCOUNTING_INVARIANT_BROKEN';
        }

        if ($health->accounts['deepestDebt'] < $this->debtAlertThreshold) {
            $alerts[] = 'DEBT_TOO_DEEP';
        }

        if ($health->shareAtZeroOrBelow() > $this->zeroBalanceAlertShare) {
            $alerts[] = 'TOO_MANY_EMPTY_ACCOUNTS';
        }

        $recovery = $health->overdraftRecoveryRate();

        // Nulo es «todavía no se ha concedido ninguno», no «no se recupera
        // ninguno». Alertar ahí sería alertar sobre datos que no existen.
        if (null !== $recovery && $recovery < $this->overdraftRecoveryAlertRate) {
            $alerts[] = 'OVERDRAFT_NOT_RECOVERED';
        }

        return $alerts;
    }

    private static function moment(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            // Un periodo ilegible es un periodo que no se pidió: el panel
            // responde igual, sobre todo el histórico.
            return null;
        }
    }
}
