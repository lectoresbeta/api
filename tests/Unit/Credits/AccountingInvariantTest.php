<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Monitoring\Domain\Service\AccountingInvariant;
use PHPUnit\Framework\TestCase;

/**
 * La invariante contable (`FEAT-CRD-012`, `decision:0006` `RN-11`).
 *
 * **Es un test, no una aspiración**, y esta clase es donde eso se hace
 * literal. Lo que comprueba no es un caso de uso: es que **ningún** caso de
 * uso pueda crear créditos sin pasar por un grifo.
 *
 * Las tres cifras llegan por dos caminos independientes —los movimientos y
 * los saldos guardados—, y esa independencia es lo que hace que la
 * comparación valga: si el saldo se calculase sumando los movimientos,
 * coincidiría siempre y no diría nada.
 */
final class AccountingInvariantTest extends TestCase
{
    private const WHEN = '2026-09-25T10:00:00+00:00';

    /**
     * El caso normal: un grifo abona, una transferencia mueve, y la
     * invariante se cumple en los dos momentos.
     */
    public function testATapIssuesAndATransferMovesNothing(): void
    {
        $transactions = new InMemoryCreditTransactions();
        $accounts = new InMemoryCreditAccounts();
        $invariant = new AccountingInvariant($transactions, $accounts);

        $autora = $this->anAccountWith($accounts, $transactions, 10, CreditTransactionReason::WELCOME_GRANT);
        $lectora = $this->anAccountWith($accounts, $transactions, 10, CreditTransactionReason::WELCOME_GRANT);

        $check = $invariant->check();
        self::assertTrue($check->holds());
        self::assertSame(20, $check->issued, 'Dos regalos de bienvenida.');
        self::assertSame(20, $check->balances);

        // Y ahora una corrección: la autora paga, la lectora cobra.
        $this->move($accounts, $transactions, $autora, -6, CreditTransactionReason::CORRECTION_CHARGED);
        $this->move($accounts, $transactions, $lectora, 6, CreditTransactionReason::CORRECTION_EARNED);

        $check = $invariant->check();
        self::assertTrue($check->holds(), 'Una transferencia no cambia la masa.');
        self::assertSame(20, $check->issued);
        self::assertSame(20, $check->moved);
        self::assertSame(20, $check->balances);
    }

    /**
     * La mitad que **ningún test de un caso de uso concreto detecta**: un
     * cobro sin el pago correspondiente.
     *
     * Es el fallo grave de verdad — significa que alguien tiene créditos que
     * nadie pagó — y visto desde su propio caso de uso parece perfecto: la
     * lectora cobró lo que le tocaba.
     */
    public function testAnEarningWithoutItsChargeBreaksTheInvariant(): void
    {
        $transactions = new InMemoryCreditTransactions();
        $accounts = new InMemoryCreditAccounts();
        $invariant = new AccountingInvariant($transactions, $accounts);

        $lectora = $this->anAccountWith($accounts, $transactions, 10, CreditTransactionReason::WELCOME_GRANT);
        $this->move($accounts, $transactions, $lectora, 6, CreditTransactionReason::CORRECTION_EARNED);

        $check = $invariant->check();

        self::assertFalse($check->holds());
        self::assertSame('TRANSFERS_DO_NOT_NET_TO_ZERO', $check->failure());
        self::assertSame(10, $check->issued, 'Los grifos no han emitido esos seis.');
        self::assertSame(16, $check->moved);
    }

    /**
     * La otra mitad: un saldo que se ha desviado de su propia historia.
     *
     * Solo se ve comparando dos fuentes, que es para lo que existen las dos.
     */
    public function testABalanceThatDriftedFromItsHistoryBreaksTheInvariant(): void
    {
        $transactions = new InMemoryCreditTransactions();
        $accounts = new InMemoryCreditAccounts();
        $invariant = new AccountingInvariant($transactions, $accounts);

        $autora = new CreditAccount(UserId::fromString('11111111-1111-4111-8111-111111111111'), $this->when());
        $movement = $autora->apply(
            CreditTransactionId::generate(),
            10,
            CreditTransactionReason::WELCOME_GRANT,
            $this->when(),
        );
        $accounts->save($autora);
        $transactions->add($movement);

        self::assertTrue($invariant->check()->holds());

        // Y ahora el saldo cambia sin que nadie apunte el movimiento, que es
        // lo que haría un contador paralelo mal mantenido.
        $autora->apply(
            CreditTransactionId::generate(),
            5,
            CreditTransactionReason::MANUAL_ADJUSTMENT,
            $this->when(),
        );
        $accounts->save($autora);

        $check = $invariant->check();

        self::assertFalse($check->holds());
        self::assertSame('BALANCES_DRIFTED_FROM_HISTORY', $check->failure());
        self::assertSame(10, $check->moved);
        self::assertSame(15, $check->balances);
    }

    /**
     * El descubierto **no rompe nada**, y esto es lo que corrige a la ficha.
     *
     * `FEAT-CRD-012` escribe la invariante como «saldos = grifos − descubierto
     * no recuperado». Ese término sobra: los saldos negativos ya están dentro
     * de la suma, y restarlos otra vez haría fallar la igualdad justo cuando
     * alguien está endeudado, que es un estado normal y previsto
     * (`FEAT-CRD-018`).
     */
    public function testDebtDoesNotBreakTheInvariant(): void
    {
        $transactions = new InMemoryCreditTransactions();
        $accounts = new InMemoryCreditAccounts();
        $invariant = new AccountingInvariant($transactions, $accounts);

        $autora = $this->anAccountWith($accounts, $transactions, 10, CreditTransactionReason::WELCOME_GRANT);
        $lectora = $this->anAccountWith($accounts, $transactions, 10, CreditTransactionReason::WELCOME_GRANT);

        // Dos correcciones que la autora no podía pagar entera: acaba en rojo.
        foreach ([8, 8] as $price) {
            $this->move($accounts, $transactions, $autora, -$price, CreditTransactionReason::CORRECTION_CHARGED);
            $this->move($accounts, $transactions, $lectora, $price, CreditTransactionReason::CORRECTION_EARNED);
        }

        self::assertSame(-6, $accounts->ofUser($autora)->balance(), 'El escenario tiene que dejarla en rojo.');

        $check = $invariant->check();

        self::assertTrue($check->holds(), 'Una deuda es un saldo negativo, no un crédito perdido.');
        self::assertSame(20, $check->balances);
    }

    /**
     * Un ajuste manual **sí** mueve la masa, y por eso es un grifo. Si se
     * contara como transferencia, la invariante empezaría a fallar y nadie
     * sabría por qué (`FEAT-MOD-005` `RN-4`).
     */
    public function testAManualAdjustmentCountsAsATap(): void
    {
        $transactions = new InMemoryCreditTransactions();
        $accounts = new InMemoryCreditAccounts();
        $invariant = new AccountingInvariant($transactions, $accounts);

        $alguien = $this->anAccountWith($accounts, $transactions, 10, CreditTransactionReason::WELCOME_GRANT);
        $this->move($accounts, $transactions, $alguien, -3, CreditTransactionReason::MANUAL_ADJUSTMENT);

        $check = $invariant->check();

        self::assertTrue($check->holds());
        self::assertSame(7, $check->issued, 'Una absorción es emisión negativa, no una transferencia.');
        self::assertSame(7, $check->balances);
    }

    private function anAccountWith(
        InMemoryCreditAccounts $accounts,
        InMemoryCreditTransactions $transactions,
        int $amount,
        CreditTransactionReason $reason,
    ): UserId {
        $userId = UserId::fromString(\sprintf('%s-1111-4111-8111-111111111111', str_pad((string) (11111111 + \count($transactions->all())), 8, '0', \STR_PAD_LEFT)));
        $account = new CreditAccount($userId, $this->when());

        $transactions->add($account->apply(CreditTransactionId::generate(), $amount, $reason, $this->when()));
        $accounts->save($account);

        return $userId;
    }

    private function move(
        InMemoryCreditAccounts $accounts,
        InMemoryCreditTransactions $transactions,
        UserId $userId,
        int $amount,
        CreditTransactionReason $reason,
    ): void {
        $account = $accounts->ofUser($userId);
        self::assertNotNull($account);

        $transactions->add($account->apply(CreditTransactionId::generate(), $amount, $reason, $this->when()));
        $accounts->save($account);
    }

    private function when(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::WHEN);
    }
}
