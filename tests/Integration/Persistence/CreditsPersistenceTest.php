<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Integration\Persistence;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The two rules of `Credits` that only the database can really enforce: that
 * the balance may go negative, and that an event is never applied twice.
 */
final class CreditsPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private CreditAccountRepository $accounts;

    private CreditTransactionRepository $transactions;

    private ProcessedEventRepository $processedEvents;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $accounts = $container->get(CreditAccountRepository::class);
        \assert($accounts instanceof CreditAccountRepository);
        $this->accounts = $accounts;

        $transactions = $container->get(CreditTransactionRepository::class);
        \assert($transactions instanceof CreditTransactionRepository);
        $this->transactions = $transactions;

        $processedEvents = $container->get(ProcessedEventRepository::class);
        \assert($processedEvents instanceof ProcessedEventRepository);
        $this->processedEvents = $processedEvents;
    }

    /**
     * `FEAT-CRD-018` says it plainly: any database constraint that stops the
     * balance going negative has to be removed. This is the test that would
     * notice if somebody added one back.
     */
    public function testTheBalanceIsStoredNegative(): void
    {
        $userId = UserId::generate();
        $account = new CreditAccount($userId, $this->now());

        $this->transactions->add($account->apply(
            CreditTransactionId::generate(),
            -7,
            CreditTransactionReason::CORRECTION_CHARGED,
            $this->now(),
        ));

        $this->accounts->save($account);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->accounts->ofUser($userId);

        self::assertNotNull($reloaded);
        self::assertSame(-7, $reloaded->balance());
        self::assertTrue($reloaded->isInDebt());
    }

    /**
     * The running total on the account has to agree with the sum of the
     * movements (`RN-1`). If they ever disagree, somebody has been editing
     * the balance.
     */
    public function testTheStoredBalanceMatchesTheSumOfItsMovements(): void
    {
        $userId = UserId::generate();
        $account = new CreditAccount($userId, $this->now());

        foreach ([[10, CreditTransactionReason::WELCOME_GRANT],
            [-6, CreditTransactionReason::CORRECTION_CHARGED],
            [4, CreditTransactionReason::CORRECTION_EARNED]] as [$amount, $reason]) {
            $this->transactions->add($account->apply(
                CreditTransactionId::generate(),
                $amount,
                $reason,
                $this->now(),
            ));
        }

        $this->accounts->save($account);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->accounts->ofUser($userId);

        self::assertNotNull($reloaded);
        self::assertSame(8, $reloaded->balance());
        self::assertSame(8, $this->transactions->balanceOf($userId));
    }

    public function testAnEventIsRecordedOnlyOncePerConsumer(): void
    {
        $eventId = CreditTransactionId::generate()->value();

        self::assertFalse($this->processedEvents->wasProcessed($eventId, 'welcome-grant'));

        $this->processedEvents->markProcessed(
            new ProcessedEvent($eventId, 'welcome-grant', 'AccountActivated', $this->now()),
        );
        $this->entityManager->flush();

        self::assertTrue($this->processedEvents->wasProcessed($eventId, 'welcome-grant'));
    }

    /**
     * La razón de que la clave sea `(eventId, consumer)` y no el identificador
     * a secas (`FEAT-CRD-011` `RN-4`): dos reglas independientes tienen que
     * poder ver el mismo hecho. Con la clave anterior, la primera en procesar
     * lo marcaba como hecho para la segunda, que no se ejecutaba nunca.
     */
    public function testTwoRulesEachSeeTheSameEvent(): void
    {
        $eventId = CreditTransactionId::generate()->value();

        $this->processedEvents->markProcessed(
            new ProcessedEvent($eventId, 'welcome-grant', 'AccountActivated', $this->now()),
        );
        $this->entityManager->flush();

        self::assertTrue($this->processedEvents->wasProcessed($eventId, 'welcome-grant'));
        self::assertFalse($this->processedEvents->wasProcessed($eventId, 'invitation-reward'));
    }

    /**
     * Idempotency does not rest on the check but on the primary key: two
     * workers can pass the check at the same time, and only one insert wins.
     */
    public function testTheDatabaseRefusesTheSameEventTwice(): void
    {
        $eventId = CreditTransactionId::generate()->value();

        $this->processedEvents->markProcessed(new ProcessedEvent($eventId, 'welcome-grant', 'AccountActivated', $this->now()));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->getConnection()->insert('credits_ctx.processed_event', [
            'event_id' => $eventId,
            'consumer' => 'welcome-grant',
            'event_name' => 'AccountActivated',
            'processed_at' => $this->now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-24 10:00:00');
    }
}
