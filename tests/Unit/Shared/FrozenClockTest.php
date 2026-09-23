<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;

final class FrozenClockTest extends TestCase
{
    public function testTheClockDoesNotMoveOnItsOwn(): void
    {
        $clock = FrozenClock::at('2026-09-24T10:00:00');

        $first = $clock->now();
        $second = $clock->now();

        self::assertSame('2026-09-24 10:00:00', $first->format('Y-m-d H:i:s'));
        self::assertEquals($first, $second);
    }

    public function testItCanBeAdvancedExplicitly(): void
    {
        $clock = FrozenClock::at('2026-09-24T10:00:00');
        $clock->advance('+30 days');

        self::assertSame('2026-10-24', $clock->now()->format('Y-m-d'));
    }
}
