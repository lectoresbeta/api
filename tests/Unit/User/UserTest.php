<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\User;

use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Account\Domain\Exception\AccountAlreadyActivated;
use LectoresBeta\User\Account\Domain\Exception\AccountIsDeleted;
use LectoresBeta\User\Account\Domain\Exception\UsernameChangedTooRecently;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\HashedPassword;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = FrozenClock::at('2026-09-24T10:00:00');
    }

    public function testRegisteringLeavesTheAccountUnverifiedAndUnableToWrite(): void
    {
        $user = $this->register();

        self::assertSame(AccountStatus::PENDING_ACTIVATION, $user->status());
        self::assertFalse($user->canWrite());
        self::assertNull($user->activatedAt());
    }

    /**
     * Google ya ha verificado la dirección, así que no queda nada que activar
     * (`OB-11`) y la cuenta nace `ACTIVE`.
     */
    public function testSigningUpWithGoogleNeedsNoActivation(): void
    {
        $user = User::registerWithGoogle(
            UserId::generate(),
            Email::fromString('beatriz@example.com'),
            Username::fromString('beatriz'),
            'google-12345',
            $this->clock->now(),
        );

        self::assertSame(AccountStatus::ACTIVE, $user->status());
        self::assertSame(AuthProvider::GOOGLE, $user->authProvider());
        self::assertTrue($user->canWrite());
        self::assertNotNull($user->activatedAt());
    }

    public function testActivatingUnlocksWriting(): void
    {
        $user = $this->register();
        $user->activate($this->clock->now());

        self::assertTrue($user->canWrite());
        self::assertEquals($this->clock->now(), $user->activatedAt());
    }

    public function testActivatingTwiceIsRefused(): void
    {
        $user = $this->register();
        $user->activate($this->clock->now());

        $this->expectException(AccountAlreadyActivated::class);

        $user->activate($this->clock->now());
    }

    public function testTheEmailIsNormalised(): void
    {
        $user = User::register(
            UserId::generate(),
            Email::fromString('  Beatriz@Example.COM '),
            Username::fromString('beatriz'),
            HashedPassword::fromHash('$2y$13$hash'),
            $this->clock->now(),
        );

        self::assertSame('beatriz@example.com', $user->email()->value());
    }

    public function testTheUsernameCanOnlyChangeEveryThirtyDays(): void
    {
        $user = $this->register();
        $user->changeUsername(Username::fromString('beatriz_a'), $this->clock->now());

        $this->clock->advance('+29 days');

        $this->expectException(UsernameChangedTooRecently::class);

        $user->changeUsername(Username::fromString('beatriz_b'), $this->clock->now());
    }

    public function testTheUsernameCanChangeAgainAfterTheCooldown(): void
    {
        $user = $this->register();
        $user->changeUsername(Username::fromString('beatriz_a'), $this->clock->now());

        $this->clock->advance('+31 days');
        $user->changeUsername(Username::fromString('beatriz_b'), $this->clock->now());

        self::assertSame('beatriz_b', $user->username()->value());
    }

    /**
     * Borrar es anonimizar: la fila sobrevive porque correcciones y
     * movimientos de créditos siguen apuntando a ella, pero **no queda ningún
     * dato personal** (`FEAT-USR-013`).
     */
    public function testDeletingLeavesNoPersonalData(): void
    {
        $user = $this->register();
        $user->activate($this->clock->now());
        $id = $user->id();

        $user->anonymise($this->clock->now());

        self::assertSame(AccountStatus::DELETED, $user->status());
        self::assertTrue($id->equals($user->id()), 'El identificador sobrevive al borrado.');
        self::assertNull($user->name());
        self::assertNull($user->description());
        self::assertNull($user->birthDate());
        self::assertNull($user->avatarUrl());
        self::assertNull($user->passwordHash());
        self::assertStringNotContainsString('beatriz', $user->email()->value());
        self::assertStringNotContainsString('beatriz', $user->username()->value());
    }

    public function testADeletedAccountRefusesEveryChange(): void
    {
        $user = $this->register();
        $user->anonymise($this->clock->now());

        $this->expectException(AccountIsDeleted::class);

        $user->activate($this->clock->now());
    }

    /**
     * La expulsión bloquea pero **no anonimiza** (`MOD-26`): borrar los datos
     * de quien es expulsado borraría también la razón por la que lo fue.
     */
    public function testExpulsionBlocksWithoutErasing(): void
    {
        $user = $this->register();
        $user->activate($this->clock->now());
        $user->block($this->clock->now());

        self::assertSame(AccountStatus::BLOCKED, $user->status());
        self::assertFalse($user->canWrite());
        self::assertSame('beatriz@example.com', $user->email()->value());
    }

    private function register(): User
    {
        return User::register(
            UserId::generate(),
            Email::fromString('beatriz@example.com'),
            Username::fromString('beatriz'),
            HashedPassword::fromHash('$2y$13$hash'),
            $this->clock->now(),
        );
    }
}
