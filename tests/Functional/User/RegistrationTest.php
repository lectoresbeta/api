<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Application\Contract\ActivationLinkProvider;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Alta y activación de extremo a extremo (`FEAT-USR-001`, `FEAT-USR-020`).
 *
 * La propiedad que más se comprueba aquí no es que el alta funcione, sino que
 * **el formulario no sirva para averiguar quién tiene cuenta** (`RN-14`). Es
 * la clase de regla que se cumple el día que se escribe y se rompe seis meses
 * después, cuando alguien añade un 409 «para que el usuario lo sepa».
 */
final class RegistrationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->client->disableReboot();
    }

    public function testSigningUpCreatesAnUnactivatedAccount(): void
    {
        $this->register('nueva@ejemplo.com');

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $user = $this->users()->ofEmail(Email::fromString('nueva@ejemplo.com'));

        self::assertNotNull($user);
        self::assertSame(AccountStatus::PENDING_ACTIVATION, $user->status());
        self::assertSame('nueva', $user->username()->value());
    }

    /**
     * El correo se normaliza en el value object, así que dos grafías son la
     * misma cuenta y la segunda alta no crea nada (`RN-1`).
     */
    public function testTheSameAddressInAnotherCaseIsTheSameAccount(): void
    {
        $this->register('Ana@Ejemplo.com');
        self::assertSame(['UserRegistered'], $this->published());

        $this->register('ana@ejemplo.com');

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertSame([], $this->published());
    }

    /**
     * Lo importante: **la respuesta es idéntica**, byte a byte, exista o no la
     * cuenta. Y no se publica un segundo `UserRegistered`, que provocaría un
     * segundo correo a alguien que no se ha registrado.
     */
    public function testRegisteringATakenAddressIsIndistinguishableFromSuccess(): void
    {
        $this->register('repetida@ejemplo.com');
        $first = $this->client->getResponse();
        self::assertSame(['UserRegistered'], $this->published());

        $this->register('repetida@ejemplo.com');
        $second = $this->client->getResponse();

        self::assertSame($first->getStatusCode(), $second->getStatusCode());
        self::assertSame($first->getContent(), $second->getContent());

        // Y, lo que no se ve desde fuera: no se publica nada, así que nadie
        // recibe un segundo correo de activación por una cuenta que ya tenía.
        self::assertSame([], $this->published());
    }

    public function testASecondAccountOnTheSameLocalPartGetsAnotherUsername(): void
    {
        $this->register('carmen@ejemplo.com');
        $this->register('carmen@otrodominio.com');

        $second = $this->users()->ofEmail(Email::fromString('carmen@otrodominio.com'));

        self::assertNotNull($second);
        self::assertSame('carmen_1', $second->username()->value());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unacceptablePasswords')]
    public function testThePasswordPolicyIsEnforcedOnTheServer(string $password): void
    {
        $this->register('debil@ejemplo.com', $password);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('WEAK_PASSWORD', $this->payload()['code']);
        self::assertNull($this->users()->ofEmail(Email::fromString('debil@ejemplo.com')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unacceptablePasswords(): iterable
    {
        yield 'demasiado corta' => ['Ab1!xyz'];
        yield 'sin mayúscula' => ['abcdef1!'];
        yield 'sin dígito' => ['Abcdefg!'];
        yield 'sin carácter especial' => ['Abcdefg1'];
    }

    public function testNoAccountIsCreatedWithoutAcceptingBothDocuments(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'sinterminos@ejemplo.com',
            'password' => 'Valida1!',
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('TERMS_NOT_ACCEPTED', $this->payload()['code']);
        self::assertNull($this->users()->ofEmail(Email::fromString('sinterminos@ejemplo.com')));
    }

    public function testAMalformedAddressIsRejectedAndLeaksNothing(): void
    {
        $this->register('no-es-un-correo');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_VALUE', $this->payload()['code']);
    }

    public function testFollowingTheActivationLinkActivatesTheAccountAndPublishesTheFact(): void
    {
        $this->register('activable@ejemplo.com');
        self::assertSame(['UserRegistered'], $this->published());

        $plain = $this->issueActivationTokenFor('activable@ejemplo.com');
        $this->activate($plain);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $user = $this->users()->ofEmail(Email::fromString('activable@ejemplo.com'));
        self::assertNotNull($user);
        self::assertSame(AccountStatus::ACTIVE, $user->status());

        // El hecho que `Credits` convierte en los 10 de bienvenida, sin que
        // `User` sepa nunca esa cifra (`FEAT-CRD-002`).
        self::assertSame(['AccountActivated'], $this->published());
    }

    /**
     * Pulsar dos veces el enlace, o que el cliente de correo lo precargue, no
     * puede ser un error (`FEAT-USR-020` `RN-3`). Y no publica el hecho otra
     * vez: no conviene apoyarse en la deduplicación de otro contexto para algo
     * que aquí se sabe.
     */
    public function testActivatingTwiceSucceedsAndPublishesTheFactOnlyOnce(): void
    {
        $this->register('doble@ejemplo.com');
        $plain = $this->issueActivationTokenFor('doble@ejemplo.com');

        $this->activate($plain);
        self::assertSame(['AccountActivated'], $this->published());

        $this->activate($plain);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([], $this->published());
    }

    public function testAnUnknownTokenIsRefusedWithoutSayingWhy(): void
    {
        $this->activate(str_repeat('a', 64));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('INVALID_ACTIVATION_TOKEN', $this->payload()['code']);
    }

    private function register(string $email, string $password = 'Valida1!'): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => $password,
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));
    }

    private function activate(string $token): void
    {
        $this->client->request('POST', '/api/v1/auth/activate', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $token],
            \JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * El token en claro solo existe dentro del correo. Aquí se pide por el
     * mismo contrato que usa `Notification` al enviarlo, en vez de fabricar
     * uno a mano: un atajo en el test haría que el test siguiera pasando el
     * día que ese camino se rompa.
     *
     * El recorrido completo, con correo incluido, está en `ActivationFlowTest`.
     */
    private function issueActivationTokenFor(string $email): string
    {
        $user = $this->users()->ofEmail(Email::fromString($email));
        self::assertNotNull($user);

        /** @var ActivationLinkProvider $links */
        $links = self::getContainer()->get(ActivationLinkProvider::class);
        $link = $links->issueFor($user->id()->value());

        self::assertNotNull($link);

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->flush();

        return $link->token;
    }

    private function users(): UserRepository
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);

        return $users;
    }

    /**
     * Los hechos publicados **por la última petición**, en orden.
     *
     * Por petición y no acumulados: Symfony reinicia los servicios que
     * implementan `ResetInterface` al terminar cada una, y el transporte en
     * memoria es uno de ellos. Sale un test mejor de todas formas, porque
     * permite afirmar «esta petición no publicó nada», que es la mitad
     * interesante de `RN-14`.
     *
     * @return list<string>
     */
    private function published(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.integration');

        return array_map(
            static function (Envelope $envelope): string {
                $event = $envelope->getMessage();
                self::assertInstanceOf(IntegrationEvent::class, $event);

                return $event->eventName();
            },
            array_values($transport->getSent()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
