<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Integration\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\HashedPassword;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * That the mapping actually works.
 *
 * A domain model can be perfect and still not survive a round trip through
 * Doctrine: an enum mapped as the wrong type, a value object the hydrator
 * cannot rebuild, a unique index that does not bite. None of that shows up in
 * a unit test, and all of it shows up here.
 *
 * Every test runs inside a transaction that is rolled back afterwards
 * (`dama/doctrine-test-bundle`), so the database is left as it was found.
 */
final class UserPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $users = $container->get(UserRepository::class);
        \assert($users instanceof UserRepository);
        $this->users = $users;
    }

    public function testAUserSurvivesARoundTrip(): void
    {
        $id = UserId::generate();

        $user = User::register(
            $id,
            Email::fromString('beatriz@example.com'),
            Username::fromString('beatriz'),
            HashedPassword::fromHash('$2y$13$not-a-real-hash'),
            new \DateTimeImmutable('2026-09-24 10:00:00'),
        );

        $this->users->save($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->users->ofId($id);

        self::assertNotNull($reloaded);
        self::assertTrue($id->equals($reloaded->id()));
        self::assertSame('beatriz@example.com', $reloaded->email()->value());
        self::assertSame('beatriz', $reloaded->username()->value());
        self::assertSame(AccountStatus::PENDING_ACTIVATION, $reloaded->status());
        self::assertSame(AuthProvider::LOCAL, $reloaded->authProvider());
        self::assertFalse($reloaded->canWrite());
    }

    public function testActivationIsPersisted(): void
    {
        $id = UserId::generate();
        $this->users->save($this->aUserCalled($id, 'carlos'));
        $this->entityManager->flush();

        $user = $this->users->ofId($id);
        self::assertNotNull($user);
        $user->activate(new \DateTimeImmutable('2026-09-24 11:00:00'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->users->ofId($id);

        self::assertNotNull($reloaded);
        self::assertSame(AccountStatus::ACTIVE, $reloaded->status());
        self::assertTrue($reloaded->canWrite());
    }

    /**
     * JSONB does not keep the order of the keys — it stores them sorted. That
     * is worth knowing before anything relies on the order of what comes back
     * out, which is why the assertion compares content and not order.
     */
    public function testTheJsonColumnRoundTrips(): void
    {
        $id = UserId::generate();
        $user = $this->aUserCalled($id, 'lucia');
        $user->updateAvatar(
            'https://cdn.example.com/a.jpg',
            'https://cdn.example.com/a-original.jpg',
            ['scale' => 1.4, 'rotation' => 90, 'offsetX' => -12, 'offsetY' => 8],
            new \DateTimeImmutable('2026-09-24 10:00:00'),
        );

        $this->users->save($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->users->ofId($id);

        self::assertNotNull($reloaded);
        self::assertEqualsCanonicalizing(
            ['scale' => 1.4, 'rotation' => 90, 'offsetX' => -12, 'offsetY' => 8],
            $reloaded->avatarCrop(),
        );
    }

    /**
     * The unique index is the guarantee, not the application check. Without
     * it two simultaneous registrations would both pass validation.
     */
    public function testTheEmailIsUniqueInTheDatabase(): void
    {
        $this->users->save($this->aUserCalled(UserId::generate(), 'primera', 'repetido@example.com'));
        $this->entityManager->flush();

        $this->users->save($this->aUserCalled(UserId::generate(), 'segunda', 'repetido@example.com'));

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    /**
     * Deleting empties every personal field but keeps the row, because
     * corrections and credit movements still point at it (`FEAT-USR-013`).
     */
    public function testAnAnonymisedAccountKeepsItsRow(): void
    {
        $id = UserId::generate();
        $this->users->save($this->aUserCalled($id, 'marta'));
        $this->entityManager->flush();

        $user = $this->users->ofId($id);
        self::assertNotNull($user);
        $user->anonymise(new \DateTimeImmutable('2026-09-24 12:00:00'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->users->ofId($id);

        self::assertNotNull($reloaded, 'La fila sobrevive: hay correcciones apuntando a ella.');
        self::assertSame(AccountStatus::DELETED, $reloaded->status());
        self::assertNull($reloaded->name());
    }

    private function aUserCalled(UserId $id, string $username, ?string $email = null): User
    {
        return User::register(
            $id,
            Email::fromString($email ?? $username.'@example.com'),
            Username::fromString($username),
            HashedPassword::fromHash('$2y$13$not-a-real-hash'),
            new \DateTimeImmutable('2026-09-24 10:00:00'),
        );
    }
}
