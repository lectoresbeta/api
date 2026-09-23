<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Application\Event\AccountActivated;
use LectoresBeta\Credits\Account\Application\Handler\GrantWelcomeCredits;
use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * El abono de bienvenida (`FEAT-CRD-002`).
 *
 * Las dos protecciones no son la misma dos veces, y esa es la razón de ser de
 * este test: la deduplicación por `eventId` no cubre una segunda activación
 * legítima, que traería un identificador nuevo.
 */
final class GrantWelcomeCreditsTest extends TestCase
{
    private InMemoryCreditAccounts $accounts;
    private InMemoryCreditTransactions $transactions;
    private InMemoryProcessedEvents $processed;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryCreditAccounts();
        $this->transactions = new InMemoryCreditTransactions();
        $this->processed = new InMemoryProcessedEvents();
    }

    public function testActivatingAnAccountPaysTheWelcomeGrant(): void
    {
        ($this->handler())($this->activation('e-1'));

        $account = $this->accounts->ofUser(self::user());

        self::assertNotNull($account);
        self::assertSame(10, $account->balance());
        self::assertCount(1, $this->transactions->all());
        self::assertSame(CreditTransactionReason::WELCOME_GRANT, $this->transactions->all()[0]->reason());
    }

    /**
     * La cuenta de créditos nace aquí (`RN-5`): `Credits` no se suscribe al
     * registro, porque una cuenta sin movimientos y un saldo de cero son
     * indistinguibles.
     */
    public function testTheCreditAccountIsCreatedByTheFirstEventThatHasAnEffect(): void
    {
        self::assertNull($this->accounts->ofUser(self::user()));

        ($this->handler())($this->activation('e-1'));

        self::assertNotNull($this->accounts->ofUser(self::user()));
    }

    public function testRedeliveringTheSameEventPaysOnlyOnce(): void
    {
        $handler = $this->handler();

        $handler($this->activation('e-1'));
        $handler($this->activation('e-1'));

        self::assertSame(10, $this->accounts->ofUser(self::user())?->balance());
        self::assertCount(1, $this->transactions->all());
    }

    /**
     * El caso que la deduplicación **no** cubre: otra activación, legítima,
     * de la misma cuenta. Trae otro `eventId` y pasaría el primer filtro sin
     * despeinarse (`RN-3`, `RN-4`).
     */
    public function testASecondDistinctActivationOfTheSameAccountPaysNothing(): void
    {
        $handler = $this->handler();

        $handler($this->activation('e-1'));
        $handler($this->activation('e-2'));

        self::assertSame(10, $this->accounts->ofUser(self::user())?->balance());
        self::assertCount(1, $this->transactions->all());
    }

    /**
     * Y queda registrado como procesado igualmente: si no, cada reentrega
     * repetiría la consulta para siempre.
     */
    public function testAnEventThatChangesNothingIsStillRecordedAsProcessed(): void
    {
        $handler = $this->handler();

        $handler($this->activation('e-1'));
        $handler($this->activation('e-2'));

        self::assertTrue($this->processed->wasProcessed('e-2', 'welcome-grant'));
    }

    /**
     * La deduplicación es por regla: otra regla de `Credits` tiene que poder
     * ver el mismo hecho (`FEAT-CRD-011` `RN-4`).
     */
    public function testTheEventIsMarkedForThisRuleOnly(): void
    {
        ($this->handler())($this->activation('e-1'));

        self::assertTrue($this->processed->wasProcessed('e-1', 'welcome-grant'));
        self::assertFalse($this->processed->wasProcessed('e-1', 'invitation-reward'));
    }

    public function testTheMovementKeepsTheOriginatingEvent(): void
    {
        ($this->handler())($this->activation('e-1'));

        self::assertSame('e-1', $this->transactions->all()[0]->eventId());
    }

    private function handler(): GrantWelcomeCredits
    {
        return new GrantWelcomeCredits(
            $this->accounts,
            $this->transactions,
            $this->processed,
            new ImmediateSession(),
            FrozenClock::at('2026-09-23T10:00:00+00:00'),
            10,
        );
    }

    private function activation(string $eventId): AccountActivated
    {
        return AccountActivated::fromPayload(
            $eventId,
            new \DateTimeImmutable('2026-09-23T10:00:00+00:00'),
            ['userId' => self::user()->value()],
        );
    }

    private static function user(): UserId
    {
        return UserId::fromString('0199c7f2-0000-7000-8000-000000000001');
    }
}

final class InMemoryCreditAccounts implements CreditAccountRepository
{
    /** @var array<string, CreditAccount> */
    private array $accounts = [];

    public function save(CreditAccount $account): void
    {
        $this->accounts[$account->userId()->value()] = $account;
    }

    public function ofUser(UserId $userId): ?CreditAccount
    {
        return $this->accounts[$userId->value()] ?? null;
    }

    public function totalBalance(): int
    {
        return array_sum(array_map(static fn (CreditAccount $a): int => $a->balance(), $this->accounts));
    }
}

final class InMemoryCreditTransactions implements CreditTransactionRepository
{
    /** @var list<CreditTransaction> */
    private array $movements = [];

    public function add(CreditTransaction $transaction): void
    {
        $this->movements[] = $transaction;
    }

    /**
     * @return list<CreditTransaction>
     */
    public function all(): array
    {
        return $this->movements;
    }

    public function historyOf(UserId $userId, int $limit = 50, int $offset = 0): array
    {
        return array_values(array_filter(
            $this->movements,
            static fn (CreditTransaction $m): bool => $m->userId()->equals($userId),
        ));
    }

    public function balanceOf(UserId $userId): int
    {
        return array_sum(array_map(
            static fn (CreditTransaction $m): int => $m->amount(),
            $this->historyOf($userId),
        ));
    }

    public function hasMovementWithReason(UserId $userId, CreditTransactionReason $reason): bool
    {
        foreach ($this->historyOf($userId) as $movement) {
            if ($movement->reason() === $reason) {
                return true;
            }
        }

        return false;
    }

    public function totalIssued(): int
    {
        return array_sum(array_map(
            static fn (CreditTransaction $m): int => $m->reason()->isTap() ? $m->amount() : 0,
            $this->movements,
        ));
    }
}

final class InMemoryProcessedEvents implements ProcessedEventRepository
{
    /** @var array<string, true> */
    private array $seen = [];

    public function wasProcessed(string $eventId, string $consumer): bool
    {
        return isset($this->seen[$eventId.'|'.$consumer]);
    }

    public function markProcessed(ProcessedEvent $event): void
    {
        $this->seen[$event->eventId().'|'.$event->consumer()] = true;
    }

    public function purgeOlderThan(\DateTimeImmutable $moment): int
    {
        return 0;
    }
}

/**
 * No hay transacción que simular: lo que estos tests comprueban es la lógica
 * de las dos protecciones, y la atomicidad se comprueba contra PostgreSQL.
 */
final class ImmediateSession implements TransactionalSession
{
    public function execute(callable $operation): mixed
    {
        return $operation();
    }
}
