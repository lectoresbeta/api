<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Application\Service\AnnounceMovement;
use LectoresBeta\Credits\Account\Application\Service\WatchDeepDebt;
use LectoresBeta\Credits\Account\Infrastructure\Logging\PsrEconomyAlert;
use LectoresBeta\Credits\Overdraft\Application\Service\TrackOverdraftUse;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Credits\Pricing\Domain\Service\CorrectabilityPolicy;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use LectoresBeta\Tests\Unit\Shared\RecordingEventPublisher;
use Psr\Log\NullLogger;

/**
 * Assembling the correctability service out of in-memory doubles.
 *
 * It exists because almost everything in `Credits` ends up touching it: a
 * price, a balance or a correction starting can all flip the answer, so the
 * handlers that do any of those things need it wired even when the test is
 * about something else.
 */
final class CreditsFixture
{
    /**
     * El aviso de movimientos, que desde `FEAT-CRD-019` también apunta cuándo
     * se gastó y cuándo se saldó un descubierto: es el único sitio que sabe
     * el saldo de antes y el de después.
     */
    public static function announcements(
        RecordingEventPublisher $published,
        ?InMemoryOverdraftGrants $overdrafts = null,
        ?Clock $clock = null,
    ): AnnounceMovement {
        return new AnnounceMovement(
            $published,
            new WatchDeepDebt(new PsrEconomyAlert(new NullLogger())),
            new TrackOverdraftUse(
                $overdrafts ?? new InMemoryOverdraftGrants(),
                new ImmediateSession(),
                $published,
                $clock ?? FrozenClock::at('2026-09-23T10:00:00+00:00'),
            ),
        );
    }

    public static function correctability(
        RecordingEventPublisher $published,
        ?InMemoryChapterPrices $prices = null,
        ?InMemoryCorrectionPrices $quotations = null,
        ?InMemoryCreditAccounts $accounts = null,
        ?Clock $clock = null,
        ?InMemoryOverdraftGrants $overdrafts = null,
        ?InMemoryCorrectionWindows $windows = null,
    ): RefreshCorrectability {
        return new RefreshCorrectability(
            $prices ?? new InMemoryChapterPrices(),
            $quotations ?? new InMemoryCorrectionPrices(),
            $windows ?? new InMemoryCorrectionWindows(),
            $accounts ?? new InMemoryCreditAccounts(),
            $overdrafts ?? new InMemoryOverdraftGrants(),
            new CorrectabilityPolicy(),
            new ImmediateSession(),
            $published,
            $clock ?? FrozenClock::at('2026-09-23T10:00:00+00:00'),
        );
    }
}
