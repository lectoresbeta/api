<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\User;

use LectoresBeta\Tests\Unit\Shared\FrozenClock;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Account\Domain\Exception\AccountNotActivated;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\HashedPassword;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Account\Infrastructure\Http\RequireActivatedAccountListener;
use LectoresBeta\User\Authentication\Infrastructure\Security\AuthenticatedUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * La política de `FEAT-USR-025`: no se escribe sin activar.
 *
 * Lo que de verdad se comprueba aquí es que **deniega por defecto**. Que una
 * ruta concreta esté bloqueada importa menos que el hecho de que una ruta
 * futura, que nadie ha escrito todavía, lo esté también sin que su autor
 * tenga que acordarse de nada.
 */
final class RequireActivatedAccountListenerTest extends TestCase
{
    public function testAnUnactivatedAccountCannotWrite(): void
    {
        $this->expectException(AccountNotActivated::class);

        $this->handle('POST', 'createWork', self::pending());
    }

    /**
     * La razón de ser del `403` propio: la interfaz tiene que poder ofrecer
     * el reenvío del correo, y para eso necesita distinguir este caso de un
     * «no tienes permiso» cualquiera (`RN-3`).
     */
    public function testTheRefusalSaysExactlyWhatIsWrong(): void
    {
        try {
            $this->handle('POST', 'createWork', self::pending());
            self::fail('Debería haber rechazado la escritura.');
        } catch (AccountNotActivated $refusal) {
            self::assertSame('ACCOUNT_NOT_ACTIVATED', $refusal->errorCode());
        }
    }

    public function testAnActivatedAccountWritesNormally(): void
    {
        $this->handle('POST', 'createWork', self::active());

        $this->expectNotToPerformAssertions();
    }

    /**
     * Leer nunca está bloqueado (`RN-5`): el catálogo, los perfiles y el
     * propio saldo se consultan desde el primer minuto.
     */
    public function testReadingIsNeverBlocked(): void
    {
        $this->handle('GET', 'getCreditBalance', self::pending());

        $this->expectNotToPerformAssertions();
    }

    /**
     * Sin esto, la regla pensada para proteger la cuenta la dejaría atrapada:
     * no podría pedir el correo que necesita para salir de ese estado
     * (`RN-6`).
     */
    public function testAnUnactivatedAccountCanStillAskForTheEmailAgain(): void
    {
        $this->handle('POST', 'resendActivationEmail', self::pending());

        $this->expectNotToPerformAssertions();
    }

    public function testAnUnactivatedAccountCanStillLogOut(): void
    {
        $this->handle('POST', 'logout', self::pending());

        $this->expectNotToPerformAssertions();
    }

    /**
     * Sin sesión no hay nada que comprobar: o la ruta es pública y responde
     * por sí misma, o el cortafuegos ya ha dicho que no.
     */
    public function testAnAnonymousRequestIsLeftAlone(): void
    {
        $this->handle('POST', 'registerUser', null);

        $this->expectNotToPerformAssertions();
    }

    private function handle(string $method, string $route, ?User $user): void
    {
        $tokens = new TokenStorage();

        if (null !== $user) {
            $tokens->setToken(new UsernamePasswordToken(
                new AuthenticatedUser($user->id()->value()),
                'api',
            ));
        }

        $listener = new RequireActivatedAccountListener(
            new Security(new ServiceLocator(['security.token_storage' => static fn (): TokenStorage => $tokens])),
            new InMemoryUsers($user),
            new FrozenClock(new \DateTimeImmutable('2026-09-25 10:00:00')),
        );

        $request = Request::create('/api/v1/whatever', $method);
        $request->attributes->set('_route', $route);

        $listener(new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn (): null => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        ));
    }

    private static function pending(): User
    {
        return User::register(
            UserId::generate(),
            Email::fromString('sinactivar@ejemplo.com'),
            Username::fromString('sinactivar'),
            HashedPassword::fromHash('irrelevante'),
            new \DateTimeImmutable(),
        );
    }

    private static function active(): User
    {
        $user = self::pending();
        $user->activate(new \DateTimeImmutable());

        return $user;
    }
}

final class InMemoryUsers implements UserRepository
{
    public function __construct(private readonly ?User $user)
    {
    }

    public function save(User $user): void
    {
    }

    public function ofId(UserId $id): ?User
    {
        return $this->user;
    }

    public function ofEmail(Email $email): ?User
    {
        return $this->user;
    }

    public function ofUsername(Username $username): ?User
    {
        return $this->user;
    }

    public function ofExternalIdentity(AuthProvider $provider, string $externalId): ?User
    {
        return $this->user;
    }

    public function ofIds(array $userIds): array
    {
        return null === $this->user ? [] : [$this->user];
    }

    public function matching(string $query, int $limit): array
    {
        return null === $this->user ? [] : [$this->user];
    }

    public function forAdministration(?string $term, int $limit, int $offset): array
    {
        return null === $this->user ? [] : [$this->user];
    }

    public function emailIsTaken(Email $email): bool
    {
        return null !== $this->user;
    }

    public function usernameIsTaken(Username $username): bool
    {
        return null !== $this->user;
    }
}
