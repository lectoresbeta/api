<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Moderation;

use LectoresBeta\Moderation\Claim\Domain\Entity\ClaimRestriction;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * El bloqueo acumulativo por reclamar en falso (`MOD-2`).
 *
 * Es una de las cuatro defensas contra usar el botón de reclamar como forma
 * de no pagar una corrección: barato la primera vez, caro la cuarta.
 */
final class ClaimRestrictionTest extends TestCase
{
    private FrozenClock $clock;

    private ClaimRestriction $restriction;

    protected function setUp(): void
    {
        $this->clock = FrozenClock::at('2026-09-24T10:00:00');
        $this->restriction = new ClaimRestriction(PartyId::generate(), $this->clock->now());
    }

    public function testSomebodyWhoHasNeverClaimedFalselyIsNotBlocked(): void
    {
        self::assertFalse($this->restriction->isBlockedAt($this->clock->now()));
    }

    public function testTheFirstDismissalBlocksForOneWeek(): void
    {
        $this->restriction->claimDismissed($this->clock->now());

        $this->clock->advance('+6 days');
        self::assertTrue($this->restriction->isBlockedAt($this->clock->now()));

        $this->clock->advance('+2 days');
        self::assertFalse($this->restriction->isBlockedAt($this->clock->now()));
    }

    public function testEachDismissalCostsOneWeekMoreThanTheLast(): void
    {
        $this->restriction->claimDismissed($this->clock->now());
        $this->clock->advance('+8 days');

        $this->restriction->claimDismissed($this->clock->now());

        $this->clock->advance('+13 days');
        self::assertTrue($this->restriction->isBlockedAt($this->clock->now()), 'La segunda bloquea dos semanas.');

        $this->clock->advance('+2 days');
        self::assertFalse($this->restriction->isBlockedAt($this->clock->now()));

        self::assertSame(2, $this->restriction->dismissedClaims());
    }

    /**
     * Reclamar en falso durante un bloqueo lo **alarga**, no lo reinicia: si
     * lo sustituyera, la tercera desestimada podría salir más barata que la
     * segunda.
     */
    public function testADismissalDuringABlockExtendsIt(): void
    {
        $this->restriction->claimDismissed($this->clock->now());

        $this->clock->advance('+3 days');
        $this->restriction->claimDismissed($this->clock->now());

        // Una semana desde el principio, y dos más desde que terminaba.
        $this->clock->advance('+17 days');
        self::assertTrue($this->restriction->isBlockedAt($this->clock->now()));

        $this->clock->advance('+2 days');
        self::assertFalse($this->restriction->isBlockedAt($this->clock->now()));
    }
}
