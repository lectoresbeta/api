<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\User;

use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Domain\Entity\UsernameAlias;
use PHPUnit\Framework\TestCase;

/**
 * Los alias de nombre de usuario (`decision:0005`). Lo interesante es la
 * diferencia entre los dos motivos: uno resuelve al perfil y se puede
 * recuperar, el otro solo bloquea.
 */
final class UsernameAliasTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = FrozenClock::at('2026-09-24T10:00:00');
    }

    public function testARenamedAliasResolvesToItsOwner(): void
    {
        $alias = UsernameAlias::afterRename(
            Username::fromString('beatriz'),
            UserId::generate(),
            $this->clock->now(),
        );

        self::assertTrue($alias->resolvesToProfileAt($this->clock->now()));
        self::assertTrue($alias->reason()->canBeReclaimed());
    }

    /**
     * El alias de una cuenta eliminada **no resuelve**: solo ocupa el nombre
     * durante 30 días. Por eso `user_id` es anulable — la fila sobrevive a la
     * cuenta que la dejó.
     */
    public function testADeletedAccountAliasOnlyBlocks(): void
    {
        $alias = UsernameAlias::afterAccountDeletion(
            Username::fromString('beatriz'),
            $this->clock->now(),
        );

        self::assertTrue($alias->isInForceAt($this->clock->now()));
        self::assertFalse($alias->resolvesToProfileAt($this->clock->now()));
        self::assertNull($alias->userId());
        self::assertFalse($alias->reason()->canBeReclaimed());
    }

    /**
     * Un alias caducado ni resuelve ni ocupa, aunque su fila siga ahí
     * esperando al comando de purga (`FEAT-USR-036`).
     */
    public function testAnExpiredAliasNeitherResolvesNorBlocks(): void
    {
        $alias = UsernameAlias::afterRename(
            Username::fromString('beatriz'),
            UserId::generate(),
            $this->clock->now(),
        );

        $this->clock->advance('+31 days');

        self::assertFalse($alias->isInForceAt($this->clock->now()));
        self::assertFalse($alias->resolvesToProfileAt($this->clock->now()));
    }

    /**
     * Recuperar el propio nombre renueva el plazo (`FEAT-USR-034` `RN-1b`).
     */
    public function testReclaimingRenewsTheReservation(): void
    {
        $alias = UsernameAlias::afterRename(
            Username::fromString('beatriz'),
            UserId::generate(),
            $this->clock->now(),
        );

        $this->clock->advance('+29 days');
        $alias->renew($this->clock->now());

        $this->clock->advance('+10 days');

        self::assertTrue($alias->isInForceAt($this->clock->now()));
    }
}
