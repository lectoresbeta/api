<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

final class CreditAccountTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = FrozenClock::at('2026-09-24T10:00:00');
    }

    public function testANewAccountStartsAtZero(): void
    {
        self::assertSame(0, $this->account()->balance());
    }

    public function testTheBalanceIsTheSumOfItsMovements(): void
    {
        $account = $this->account();

        $this->apply($account, 10, CreditTransactionReason::WELCOME_GRANT);
        $this->apply($account, -6, CreditTransactionReason::CORRECTION_CHARGED);
        $this->apply($account, 4, CreditTransactionReason::CORRECTION_EARNED);

        self::assertSame(8, $account->balance());
    }

    /**
     * El corrector cobra siempre (`RN-9`), así que el autor puede quedar en
     * negativo. Ninguna restricción lo impide, y eso es deliberado.
     */
    public function testTheBalanceMayGoNegative(): void
    {
        $account = $this->account();

        $this->apply($account, 4, CreditTransactionReason::WELCOME_GRANT);
        $this->apply($account, -6, CreditTransactionReason::CORRECTION_CHARGED);

        self::assertSame(-2, $account->balance());
        self::assertTrue($account->isInDebt());
    }

    /**
     * Con saldo negativo no se abren correcciones nuevas, pero sí se puede
     * corregir: es la única forma de salir del descubierto (`RN-10`).
     */
    public function testInDebtItCannotAffordAnythingButCanStillEarn(): void
    {
        $account = $this->account();
        $this->apply($account, -2, CreditTransactionReason::CORRECTION_CHARGED);

        self::assertFalse($account->canAfford(2));

        $this->apply($account, 6, CreditTransactionReason::CORRECTION_EARNED);

        self::assertFalse($account->isInDebt());
        self::assertTrue($account->canAfford(4));
    }

    public function testAMovementRecordsTheEventThatCausedIt(): void
    {
        $account = $this->account();

        $transaction = $account->apply(
            CreditTransactionId::generate(),
            10,
            CreditTransactionReason::WELCOME_GRANT,
            $this->clock->now(),
            'f81d4fae-7dec-71d0-a765-00a0c91e6bf6',
        );

        self::assertSame(10, $transaction->amount());
        self::assertSame('f81d4fae-7dec-71d0-a765-00a0c91e6bf6', $transaction->eventId());
        self::assertTrue($transaction->reason()->isTap());
    }

    public function testInvitationRewardsAreCounted(): void
    {
        $account = $this->account();

        $this->apply($account, 5, CreditTransactionReason::INVITATION_REWARD);
        $this->apply($account, 5, CreditTransactionReason::INVITATION_REWARD);

        self::assertSame(2, $account->invitationRewards());
    }

    /**
     * Una corrección mueve créditos entre dos cuentas y no cambia la masa
     * total. Es la invariante contable de `RN-11` en su forma más pequeña.
     */
    public function testACorrectionIsATransfer(): void
    {
        $autor = $this->account();
        $lector = $this->account();

        $this->apply($autor, 10, CreditTransactionReason::WELCOME_GRANT);
        $this->apply($lector, 10, CreditTransactionReason::WELCOME_GRANT);

        $this->apply($autor, -6, CreditTransactionReason::CORRECTION_CHARGED);
        $this->apply($lector, 6, CreditTransactionReason::CORRECTION_EARNED);

        self::assertSame(20, $autor->balance() + $lector->balance());
    }

    /**
     * La congelación de `RN-8b`: una fecha, y las preguntas se contestan
     * contra ella.
     */
    public function testTheFreezeExpiresOnItsOwn(): void
    {
        $account = $this->account();
        $now = $this->clock->now();
        $until = $now->modify('+7 days');

        self::assertTrue($account->freezeDebtUntil($until, $now, $now));

        self::assertTrue($account->isDebtFrozenAt($now->modify('+6 days')));
        self::assertFalse($account->isDebtFrozenAt($until), 'Vencido el plazo, se apaga sola.');
        self::assertFalse($account->isDebtFrozenAt($now->modify('+8 days')));
    }

    /**
     * Congelar **nunca acorta**: dos sanciones solapadas dejan la fecha más
     * lejana. Y no se anuncia dos veces lo mismo.
     */
    public function testOverlappingSanctionsKeepTheFurthestDate(): void
    {
        $account = $this->account();
        $now = $this->clock->now();
        $lejos = $now->modify('+30 days');

        self::assertTrue($account->freezeDebtUntil($lejos, $now, $now));
        self::assertFalse(
            $account->freezeDebtUntil($now->modify('+3 days'), $now->modify('+1 day'), $now),
            'Una sanción más corta no acorta la congelación ni vuelve a anunciarse.',
        );

        self::assertTrue($account->isDebtFrozenAt($now->modify('+20 days')));
    }

    /**
     * El vaivén que la cola provocaría si levantar borrase la fecha en vez de
     * apuntar la suya: reentregar el hecho de imponer volvería a congelar lo
     * que el de levantar acaba de descongelar, y así para siempre.
     */
    public function testReplayingAnImpositionDoesNotReviveALiftedFreeze(): void
    {
        $account = $this->account();
        $now = $this->clock->now();
        $impuesta = $now;
        $until = $now->modify('+7 days');

        self::assertTrue($account->freezeDebtUntil($until, $impuesta, $now));
        self::assertTrue($account->thawDebt($now->modify('+1 day'), $now->modify('+1 day')));
        self::assertFalse($account->isDebtFrozenAt($now->modify('+2 days')));

        // La reentrega, con la misma fecha de hecho que la primera vez.
        self::assertFalse($account->freezeDebtUntil($until, $impuesta, $now->modify('+2 days')));
        self::assertFalse($account->isDebtFrozenAt($now->modify('+2 days')), 'Sigue descongelada.');

        // Y reentregar el levantamiento tampoco anuncia nada nuevo.
        self::assertFalse($account->thawDebt($now->modify('+1 day'), $now->modify('+2 days')));
    }

    /**
     * Una sanción **posterior** al levantamiento sí vuelve a congelar: es
     * otra, no la misma reentregada.
     */
    public function testALaterSanctionFreezesAgain(): void
    {
        $account = $this->account();
        $now = $this->clock->now();

        $account->freezeDebtUntil($now->modify('+7 days'), $now, $now);
        $account->thawDebt($now->modify('+1 day'), $now->modify('+1 day'));

        $despues = $now->modify('+2 days');
        self::assertTrue($account->freezeDebtUntil($despues->modify('+7 days'), $despues, $despues));
        self::assertTrue($account->isDebtFrozenAt($despues));
    }

    private function account(): CreditAccount
    {
        return new CreditAccount(UserId::generate(), $this->clock->now());
    }

    private function apply(CreditAccount $account, int $amount, CreditTransactionReason $reason): void
    {
        $account->apply(CreditTransactionId::generate(), $amount, $reason, $this->clock->now());
    }
}
