<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Application\Event\FeedbackSubmitted;
use LectoresBeta\Credits\Account\Application\Handler\ChargeCorrectionOnFeedbackSubmitted;
use LectoresBeta\Credits\Account\Application\Service\AnnounceMovement;
use LectoresBeta\Credits\Account\Application\Service\WatchDeepDebt;
use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Account\Infrastructure\Logging\PsrEconomyAlert;
use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionPrice;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use LectoresBeta\Tests\Unit\Shared\RecordingEventPublisher;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * El único momento en que los créditos se mueven (`FEAT-CRD-006`).
 *
 * Lo que se defiende aquí es la invariante contable del contexto: una
 * corrección **transfiere**, no emite. Si alguna vez abonara sin cargar,
 * habría alguien con créditos que nadie pagó y la suma de todos los saldos
 * dejaría de cuadrar con lo que los grifos han emitido.
 */
final class ChargeCorrectionTest extends TestCase
{
    private const AUTHOR = '0199c7f2-0000-7000-8000-00000000000a';
    private const READER = '0199c7f2-0000-7000-8000-00000000000b';
    private const CHAPTER = '0199c7f2-0000-7000-8000-00000000000c';
    private const WORK = '0199c7f2-0000-7000-8000-00000000000d';

    private InMemoryCreditAccounts $accounts;
    private InMemoryCreditTransactions $transactions;
    private InMemoryCorrectionPrices $quotations;
    private InMemoryChapterPrices $prices;
    private InMemoryProcessedEvents $processed;
    private RecordingEventPublisher $published;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryCreditAccounts();
        $this->transactions = new InMemoryCreditTransactions();
        $this->quotations = new InMemoryCorrectionPrices();
        $this->prices = new InMemoryChapterPrices();
        $this->processed = new InMemoryProcessedEvents();
        $this->published = new RecordingEventPublisher();
    }

    public function testTheAuthorPaysExactlyWhatTheReaderEarns(): void
    {
        $this->accountWith(self::AUTHOR, 30);
        $this->accountWith(self::READER, 4);
        $this->quotedAt(6);

        ($this->handler())($this->delivery('e-1'));

        self::assertSame(24, $this->balanceOf(self::AUTHOR));
        self::assertSame(10, $this->balanceOf(self::READER));

        $movements = $this->transactions->all();
        self::assertCount(2, $movements);
        self::assertSame(0, $movements[0]->amount() + $movements[1]->amount(), 'Una corrección mueve créditos; no los crea.');
        self::assertSame(CreditTransactionReason::CORRECTION_CHARGED, $movements[0]->reason());
        self::assertSame(CreditTransactionReason::CORRECTION_EARNED, $movements[1]->reason());
    }

    /**
     * `RN-1`: el importe es el anotado al empezar, no el vigente. Es lo que
     * da sentido a anotarlo: el compromiso se fija cuando se adquiere.
     */
    public function testAnEnlargedChapterDoesNotChangeWhatWasQuoted(): void
    {
        $this->accountWith(self::AUTHOR, 30);
        $this->quotedAt(15);

        // Mientras el lector escribía, el capítulo creció hasta el tope.
        $this->chapterPricedAt(40_000, 2000);
        self::assertSame(ChapterPricing::MAX_PRICE, $this->prices->ofChapter(ChapterId::fromString(self::CHAPTER))?->price());

        ($this->handler())($this->delivery('e-1'));

        self::assertSame(15, 30 - $this->balanceOf(self::AUTHOR), 'Se cobra lo anotado, no lo que vale hoy.');
        self::assertSame(15, $this->balanceOf(self::READER));
    }

    /**
     * **La regla que sostiene el producto entero.** Un lector nunca trabaja
     * sin cobrar, aunque el autor no tenga con qué pagarle.
     */
    public function testTheReaderIsPaidEvenIfTheAuthorCannotAffordIt(): void
    {
        $this->accountWith(self::AUTHOR, 1);
        $this->quotedAt(6);

        ($this->handler())($this->delivery('e-1'));

        self::assertSame(-5, $this->balanceOf(self::AUTHOR));
        self::assertSame(6, $this->balanceOf(self::READER));

        $crossings = $this->published->payloadsOf('CreditBalanceWentNegative');
        self::assertCount(1, $crossings);
        self::assertSame(-5, $crossings[0]['balance']);
    }

    /**
     * El cruce es un momento, no un estado: quien ya está en negativo no
     * vuelve a cruzarlo con cada cargo.
     */
    public function testAnAccountAlreadyInDebtDoesNotCrossAgain(): void
    {
        $this->accountWith(self::AUTHOR, -3);
        $this->quotedAt(6);

        ($this->handler())($this->delivery('e-1'));

        self::assertSame(-9, $this->balanceOf(self::AUTHOR));
        self::assertSame([], $this->published->payloadsOf('CreditBalanceWentNegative'));
    }

    public function testTheSameDeliveryTwiceChargesOnce(): void
    {
        $this->accountWith(self::AUTHOR, 30);
        $this->quotedAt(6);

        ($this->handler())($this->delivery('e-1'));
        ($this->handler())($this->delivery('e-1'));

        self::assertCount(2, $this->transactions->all());
        self::assertSame(24, $this->balanceOf(self::AUTHOR));
    }

    /**
     * `FEAT-CRD-011` `RN-4`: el mismo hecho lo miran varias reglas, y la
     * primera en procesarlo no puede cerrarlo para las demás.
     */
    public function testTheEventIsClosedForThisRuleOnly(): void
    {
        $this->accountWith(self::AUTHOR, 30);
        $this->quotedAt(6);

        ($this->handler())($this->delivery('e-1'));

        self::assertTrue($this->processed->wasProcessed('e-1', 'correction-charge'));
        self::assertFalse($this->processed->wasProcessed('e-1', 'invitation-reward'));
    }

    public function testTheQuotationIsConsumed(): void
    {
        $this->accountWith(self::AUTHOR, 30);
        $this->quotedAt(6);

        ($this->handler())($this->delivery('e-1'));

        self::assertNull($this->quotations->quoted(ChapterId::fromString(self::CHAPTER), UserId::fromString(self::READER)));
    }

    /**
     * `RN-7`: sin anotación no se inventa un importe ni se deja al lector sin
     * cobrar. Se reconstruye con el precio vigente y **queda dicho** que fue
     * una reconstrucción, porque dentro de seis meses nadie distinguiría una
     * reparación de lo real.
     */
    public function testADeliveryWithNoQuotationIsRebuiltAndSaysSo(): void
    {
        $this->accountWith(self::AUTHOR, 30);
        $this->chapterPricedAt(3000, 300);

        ($this->handler())($this->delivery('e-1'));

        self::assertSame(6, $this->balanceOf(self::READER));
        self::assertTrue($this->transactions->all()[0]->metadata()['reconstructedPrice']);
    }

    /**
     * Ni anotación ni precio conocido: el suelo. Prefiere pagar de menos a no
     * pagar, porque el trabajo ya está hecho.
     */
    public function testWithNothingKnownTheFloorApplies(): void
    {
        ($this->handler())($this->delivery('e-1'));

        self::assertSame(ChapterPricing::MIN_PRICE, $this->balanceOf(self::READER));
    }

    public function testTheMovementsPointBackAtTheDeliveryThatCausedThem(): void
    {
        $this->accountWith(self::AUTHOR, 30);
        $this->quotedAt(6);

        ($this->handler())($this->delivery('e-1'));

        foreach ($this->transactions->all() as $movement) {
            self::assertSame('e-1', $movement->eventId());
            self::assertSame(self::CHAPTER, $movement->metadata()['chapterId']);
        }
    }

    public function testBothPeopleAreToldWhatHappenedAndWhatTheyHaveNow(): void
    {
        $this->accountWith(self::AUTHOR, 30);
        $this->quotedAt(6);

        ($this->handler())($this->delivery('e-1'));

        $spent = $this->published->payloadsOf('CreditsSpent');
        $added = $this->published->payloadsOf('CreditsAdded');

        self::assertCount(1, $spent);
        self::assertSame(6, $spent[0]['amount'], 'Lo gastado se dice en positivo.');
        self::assertSame(24, $spent[0]['balance']);
        self::assertSame(6, $added[0]['amount']);
        self::assertCount(2, $this->published->payloadsOf('CreditBalanceChanged'));
    }

    private function handler(): ChargeCorrectionOnFeedbackSubmitted
    {
        return new ChargeCorrectionOnFeedbackSubmitted(
            $this->accounts,
            $this->transactions,
            $this->quotations,
            $this->prices,
            $this->processed,
            new AnnounceMovement($this->published, new WatchDeepDebt(new PsrEconomyAlert(new NullLogger()))),
            CreditsFixture::correctability($this->published, $this->prices, $this->quotations, $this->accounts),
            new ChapterPricing(),
            new ImmediateSession(),
            FrozenClock::at('2026-09-23T10:00:00+00:00'),
        );
    }

    private function delivery(string $eventId): FeedbackSubmitted
    {
        return FeedbackSubmitted::fromPayload($eventId, $this->now(), [
            'correctionId' => '0199c7f2-0000-7000-8000-00000000000e',
            'chapterId' => self::CHAPTER,
            'workId' => self::WORK,
            'authorId' => self::AUTHOR,
            'readerId' => self::READER,
        ]);
    }

    private function accountWith(string $userId, int $balance): void
    {
        $account = new CreditAccount(UserId::fromString($userId), $this->now());

        if (0 !== $balance) {
            $account->apply(
                CreditTransactionId::generate(),
                $balance,
                CreditTransactionReason::MANUAL_ADJUSTMENT,
                $this->now(),
            );
        }

        $this->accounts->save($account);
    }

    private function quotedAt(int $amount): void
    {
        $this->quotations->save(new CorrectionPrice(
            ChapterId::fromString(self::CHAPTER),
            UserId::fromString(self::READER),
            UserId::fromString(self::AUTHOR),
            $amount,
            $this->now(),
        ));
    }

    private function chapterPricedAt(int $words, int $requiredWords): void
    {
        $this->prices->save(new ChapterPrice(
            ChapterId::fromString(self::CHAPTER),
            WorkId::fromString(self::WORK),
            UserId::fromString(self::AUTHOR),
            1,
            $words,
            $requiredWords,
            new ChapterPricing(),
            $this->now(),
        ));
    }

    private function balanceOf(string $userId): int
    {
        return $this->accounts->ofUser(UserId::fromString($userId))?->balance() ?? 0;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-23T10:00:00+00:00');
    }
}
