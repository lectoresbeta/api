<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionPrice;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\Service\CorrectabilityPolicy;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Tests\Unit\Shared\RecordingEventPublisher;
use PHPUnit\Framework\TestCase;

/**
 * Si un capítulo admite correcciones ahora mismo (`FEAT-CRD-009`).
 *
 * Es el único dato de la economía que sale del contexto, y sale como **un
 * booleano**: `Feedback` abre el panel sin saber lo que cuesta nada ni lo que
 * tiene nadie.
 */
final class CorrectabilityTest extends TestCase
{
    private const AUTHOR = '0199c7f2-0000-7000-8000-00000000001a';
    private const WORK = '0199c7f2-0000-7000-8000-00000000001d';

    private InMemoryChapterPrices $prices;
    private InMemoryCorrectionPrices $quotations;
    private InMemoryCreditAccounts $accounts;
    private RecordingEventPublisher $published;

    protected function setUp(): void
    {
        $this->prices = new InMemoryChapterPrices();
        $this->quotations = new InMemoryCorrectionPrices();
        $this->accounts = new InMemoryCreditAccounts();
        $this->published = new RecordingEventPublisher();
    }

    public function testAChapterIsCorrectableWhileTheAuthorCanPayForIt(): void
    {
        $policy = new CorrectabilityPolicy();

        self::assertTrue($policy->allows(doorOpen: true, authorBalance: 6, price: 6, openCorrections: 0));
        self::assertFalse($policy->allows(doorOpen: true, authorBalance: 5, price: 6, openCorrections: 0));
        self::assertFalse(
            $policy->allows(doorOpen: true, authorBalance: -1, price: 2, openCorrections: 0),
            'En negativo, nada es corregible.',
        );
    }

    /**
     * `FEAT-WRK-016`: la primera condición no es el dinero, es que la obra
     * admita correcciones.
     *
     * Sin esto, los capítulos de un borrador salían corregibles con solo
     * tener saldo, `Feedback` proyectaba esa respuesta y el panel se abría
     * contra ella — para que el rechazo llegara después, desde `Work`.
     */
    public function testAClosedWorkAdmitsNothingHoweverRichItsAuthorIs(): void
    {
        $policy = new CorrectabilityPolicy();

        self::assertFalse($policy->allows(doorOpen: false, authorBalance: 1000, price: 2, openCorrections: 0));
        self::assertFalse(
            $policy->allows(doorOpen: false, authorBalance: 1000, price: 2, openCorrections: 0, overdraftGranted: true),
            'Ni el descubierto deliberado abre una puerta cerrada: concede saldo, no permiso.',
        );
    }

    /**
     * El tope acota la deuda que puede provocar una carrera **sin apartar un
     * solo crédito**: tres lectores pueden empezar sobre un saldo que cubre a
     * uno, y los tres cobrarán.
     */
    public function testAChapterStopsAdmittingCorrectionsAtThree(): void
    {
        $policy = new CorrectabilityPolicy();

        self::assertTrue($policy->allows(true, 100, 2, 2));
        self::assertFalse($policy->allows(true, 100, 2, 3));
    }

    /**
     * Lo único de la aritmética de `Credits` que sale de `Credits`: cuánto
     * trabajo produce enseñar una obra, que es con lo que ordena el catálogo
     * (`decision:0008`). No es un saldo y no es un precio.
     */
    public function testHowManyCorrectionsTheAuthorCanPayForLeavesTheContext(): void
    {
        $policy = new CorrectabilityPolicy();

        self::assertSame(3, $policy->affordableCorrections(authorBalance: 10, price: 3));
        self::assertSame(0, $policy->affordableCorrections(authorBalance: 2, price: 3));
        self::assertSame(0, $policy->affordableCorrections(authorBalance: -5, price: 3));

        // El tope impide que un autor con mucho saldo monopolice el catálogo,
        // y de paso hace que por encima de diez todos se parezcan.
        self::assertSame(
            CorrectabilityPolicy::MAX_AFFORDABLE_CORRECTIONS,
            $policy->affordableCorrections(authorBalance: 5_000, price: 2),
        );
    }

    public function testOnlyChangesAreAnnounced(): void
    {
        $this->balanceOf(30);
        $chapter = $this->chapter(1);

        $this->refresh();
        self::assertCount(1, $this->published->payloadsOf('ChapterCorrectabilityChanged'));
        self::assertTrue($chapter->isCorrectable());

        // Nada ha cambiado: recalcular no es un hecho.
        $this->refresh();
        self::assertCount(1, $this->published->payloadsOf('ChapterCorrectabilityChanged'));
    }

    public function testSpendingTheBalanceTakesEveryChapterOutOfReachAtOnce(): void
    {
        $this->balanceOf(30);
        $this->chapter(1);
        $this->chapter(2);

        $this->refresh();
        self::assertCount(2, $this->published->payloadsOf('ChapterCorrectabilityChanged'));

        $this->balanceOf(0);
        $this->refresh();

        $announcements = $this->published->payloadsOf('ChapterCorrectabilityChanged');
        self::assertCount(4, $announcements);
        self::assertFalse($announcements[2]['correctable']);
        self::assertFalse($announcements[3]['correctable']);
    }

    /**
     * Lo que **no** lleva el evento. Ningún contexto ajeno tiene por qué
     * conocer un saldo ni un precio, y es lo único que mantiene a `Feedback`
     * fuera de la economía.
     */
    public function testTheAnnouncementCarriesNoMoney(): void
    {
        $this->balanceOf(30);
        $this->chapter(1);
        $this->refresh();

        $payload = $this->published->payloadsOf('ChapterCorrectabilityChanged')[0];

        self::assertSame(['chapterId', 'workId', 'correctable', 'affordableCorrections', 'changedAt'], array_keys($payload));
    }

    public function testThreeOpenCorrectionsCloseTheChapterAndFinishingOneReopensIt(): void
    {
        $this->balanceOf(30);
        $chapter = $this->chapter(1);
        $this->refresh();

        $quotations = [];

        for ($i = 0; $i < 3; ++$i) {
            $quotations[] = $this->quotationOn($chapter, $i);
        }

        $this->refresh();
        self::assertFalse($chapter->isCorrectable());

        $this->quotations->discard($quotations[0]);
        $this->refresh();

        self::assertTrue($chapter->isCorrectable());
    }

    private function refresh(): void
    {
        CreditsFixture::correctability($this->published, $this->prices, $this->quotations, $this->accounts)
            ->forAuthor(UserId::fromString(self::AUTHOR));
    }

    private function chapter(int $position): ChapterPrice
    {
        $chapter = new ChapterPrice(
            ChapterId::fromString(\sprintf('0199c7f2-0000-7000-8000-0000000000%02d', $position)),
            WorkId::fromString(self::WORK),
            UserId::fromString(self::AUTHOR),
            $position,
            3000,
            300,
            new ChapterPricing(),
            $this->now(),
        );

        $this->prices->save($chapter);

        return $chapter;
    }

    private function quotationOn(ChapterPrice $chapter, int $reader): CorrectionPrice
    {
        $quotation = new CorrectionPrice(
            $chapter->chapterId(),
            UserId::fromString(\sprintf('0199c7f2-0000-7000-8000-0000000001%02d', $reader)),
            UserId::fromString(self::AUTHOR),
            $chapter->price(),
            $this->now(),
        );

        $this->quotations->save($quotation);

        return $quotation;
    }

    private function balanceOf(int $balance): void
    {
        $account = new CreditAccount(UserId::fromString(self::AUTHOR), $this->now());

        if (0 !== $balance) {
            $account->apply(CreditTransactionId::generate(), $balance, CreditTransactionReason::MANUAL_ADJUSTMENT, $this->now());
        }

        $this->accounts->save($account);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-23T10:00:00+00:00');
    }
}
