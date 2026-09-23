<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Credits\Pricing\Domain\Service\CorrectabilityPolicy;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use LectoresBeta\Tests\Unit\Shared\RecordingEventPublisher;

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
    public static function correctability(
        RecordingEventPublisher $published,
        ?InMemoryChapterPrices $prices = null,
        ?InMemoryCorrectionPrices $quotations = null,
        ?InMemoryCreditAccounts $accounts = null,
        ?Clock $clock = null,
    ): RefreshCorrectability {
        return new RefreshCorrectability(
            $prices ?? new InMemoryChapterPrices(),
            $quotations ?? new InMemoryCorrectionPrices(),
            $accounts ?? new InMemoryCreditAccounts(),
            new CorrectabilityPolicy(),
            new ImmediateSession(),
            $published,
            $clock ?? FrozenClock::at('2026-09-23T10:00:00+00:00'),
        );
    }
}
