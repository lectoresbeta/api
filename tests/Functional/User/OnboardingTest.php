<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Profile\Domain\Event\LiteraryPreferencesUpdated;
use LectoresBeta\User\Profile\Domain\Repository\LiteraryPreferenceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * El onboarding (`FEAT-USR-022`, `FEAT-USR-023`).
 *
 * Y, de paso, la primera comprobación de extremo a extremo de
 * [`FEAT-USR-025`](../../../docs/features/user/FEAT-USR-025-block-writes-until-activation.md):
 * estos dos pasos son **escrituras** que una cuenta sin activar sí puede
 * hacer. Hasta ahora la excepción existía en una lista y nada la ejercía.
 */
final class OnboardingTest extends WebTestCase
{
    private const PASSWORD = 'Valida1!';

    private KernelBrowser $client;
    private string $run;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(4));
    }

    public function testTheCatalogueOfGenresIsPublic(): void
    {
        $this->client->request('GET', '/api/v1/genres');

        self::assertResponseIsSuccessful();

        $genres = $this->payload()['genres'];

        self::assertIsArray($genres);
        self::assertNotEmpty($genres, 'El catálogo lo siembra una migración; si está vacío, no se ha aplicado.');
        self::assertSame(['code', 'name'], array_keys((array) $genres[0]));
    }

    /**
     * La razón de ser de `GET /me/onboarding`: se puede abandonar y retomar.
     */
    public function testAFreshAccountIsWaitingOnTheFirstStep(): void
    {
        $token = $this->signUpAndSignIn();

        $this->get('/api/v1/me/onboarding', $token);

        self::assertResponseIsSuccessful();
        self::assertSame('PROFILE_PENDING', $this->payload()['status']);
        self::assertFalse($this->payload()['completed']);

        // La pantalla saluda por el nombre de usuario, asignado a partir del
        // correo al registrarse.
        self::assertIsString($this->payload()['username']);
    }

    /**
     * **Lo que hasta ahora no comprobaba nada**: una cuenta en
     * `PENDING_ACTIVATION` ejecutando una escritura exenta.
     */
    public function testAnUnactivatedAccountCompletesBothSteps(): void
    {
        $token = $this->signUpAndSignIn();

        $this->put('/api/v1/me/onboarding/profile', $token, ['name' => 'Ana García', 'birthDate' => '1990-05-17']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->get('/api/v1/me/onboarding', $token);
        self::assertSame('GENRES_PENDING', $this->payload()['status']);

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'FANTASY', 'ROMANCE']]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->get('/api/v1/me/onboarding', $token);
        self::assertSame('SUGGESTIONS_PENDING', $this->payload()['status']);
    }

    /**
     * `DateTimeImmutable` convierte alegremente `2000-02-30` en el 1 de
     * marzo. Guardaría una fecha que nadie escribió y haría a esa persona un
     * día mayor, sin que nada lo delatase.
     */
    public function testAnImpossibleDateIsRefusedInsteadOfRolledOver(): void
    {
        $token = $this->signUpAndSignIn();

        $this->put('/api/v1/me/onboarding/profile', $token, ['name' => 'Ana', 'birthDate' => '2000-02-30']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_VALUE', $this->payload()['code']);
    }

    public function testAFutureDateIsRefused(): void
    {
        $token = $this->signUpAndSignIn();

        $this->put('/api/v1/me/onboarding/profile', $token, ['name' => 'Ana', 'birthDate' => '2999-01-01']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $token = $this->signUpAndSignIn();

        $this->put('/api/v1/me/onboarding/profile', $token, ['name' => '   ', 'birthDate' => '1990-05-17']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * El botón deshabilitado del cliente es una cortesía, no una garantía
     * (`FEAT-USR-023` `RN-1`).
     */
    public function testFewerThanThreeGenresAreRefusedByTheServer(): void
    {
        $token = $this->completedFirstStep();

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'FANTASY']]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('NOT_ENOUGH_GENRES', $this->payload()['code']);
    }

    /**
     * Tres géneros repetidos son **un** género: no alcanzan el mínimo, y
     * decir que sí sería dejar pasar una selección que el cliente construyó
     * mal.
     */
    public function testRepeatedGenresDoNotCountTowardsTheMinimum(): void
    {
        $token = $this->completedFirstStep();

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'drama', 'DRAMA']]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('NOT_ENOUGH_GENRES', $this->payload()['code']);
    }

    /**
     * Se rechaza **y se nombra** (`RN-3`). Ignorarlo en silencio dejaría a
     * alguien creyendo que eligió cuatro cosas.
     */
    public function testAnUnknownGenreIsRefusedAndNamed(): void
    {
        $token = $this->completedFirstStep();

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'FANTASY', 'ROMANCE', 'ESTEGENERONOEXISTE']]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_GENRE', $this->payload()['code']);
        self::assertStringContainsString('ESTEGENERONOEXISTE', (string) $this->payload()['detail']);
    }

    /**
     * El paso 2 necesita la edad del paso 1: de ella depende qué se le puede
     * enseñar a esa persona.
     */
    public function testTheGenresStepCannotJumpAheadOfTheProfileStep(): void
    {
        $token = $this->signUpAndSignIn();

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'FANTASY', 'ROMANCE']]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ONBOARDING_STEP_OUT_OF_ORDER', $this->payload()['code']);
    }

    /**
     * La selección **sustituye**, no se acumula: quien quita un género espera
     * que desaparezca.
     */
    public function testChoosingGenresAgainReplacesTheSelection(): void
    {
        $token = $this->completedFirstStep();

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'FANTASY', 'ROMANCE']]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['HUMOUR', 'HORROR', 'THRILLER']]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $stored = $this->storedGenresOf($token);
        sort($stored);

        self::assertSame(['HORROR', 'HUMOUR', 'THRILLER'], $stored, 'La selección debe sustituir, no acumularse.');
    }

    /**
     * Y **conservando uno de los que ya había**, que parece el mismo caso y
     * no lo es: sustituir borrando todo y volviendo a insertar funciona
     * mientras las dos listas no se solapen, y falla en cuanto lo hacen.
     */
    public function testChoosingAgainCanKeepOneOfTheGenres(): void
    {
        $token = $this->completedFirstStep();

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'FANTASY', 'ROMANCE']]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'HORROR', 'THRILLER']]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $stored = $this->storedGenresOf($token);
        sort($stored);

        self::assertSame(['DRAMA', 'HORROR', 'THRILLER'], $stored);
    }

    /**
     * El hecho que `Community` necesita para sugerir autores. Lleva **la
     * selección entera** y no lo que cambió: un consumidor que tuviera que
     * aplicar deltas acabaría con un conjunto equivocado el primer mensaje
     * que se perdiera, y sin forma de notarlo.
     */
    public function testChoosingGenresPublishesTheWholeSelection(): void
    {
        $token = $this->completedFirstStep();

        $this->put('/api/v1/me/onboarding/genres', $token, ['genres' => ['DRAMA', 'FANTASY', 'ROMANCE']]);

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.integration');
        $sent = $transport->getSent();

        self::assertCount(1, $sent);

        $event = $sent[0]->getMessage();
        self::assertInstanceOf(LiteraryPreferencesUpdated::class, $event);

        $payload = $event->payload();
        self::assertSame(['DRAMA', 'FANTASY', 'ROMANCE'], $payload['genres']);
    }

    public function testTheOnboardingRequiresASession(): void
    {
        $this->client->request('GET', '/api/v1/me/onboarding');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return list<string>
     */
    private function storedGenresOf(string $token): array
    {
        $this->get('/api/v1/me/onboarding', $token);

        /** @var LiteraryPreferenceRepository $preferences */
        $preferences = self::getContainer()->get(LiteraryPreferenceRepository::class);

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $user = $users->ofEmail(Email::fromString(\sprintf('onboarding+%s@ejemplo.com', $this->run)));

        self::assertNotNull($user);

        return $preferences->codesOf($user->id());
    }

    private function completedFirstStep(): string
    {
        $token = $this->signUpAndSignIn();

        $this->put('/api/v1/me/onboarding/profile', $token, ['name' => 'Ana García', 'birthDate' => '1990-05-17']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $token;
    }

    private function signUpAndSignIn(): string
    {
        $email = \sprintf('onboarding+%s@ejemplo.com', $this->run);

        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => self::PASSWORD,
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => \sprintf('198.51.%d.%d', random_int(0, 255), random_int(1, 254)),
        ], content: json_encode(['email' => $email, 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return (string) $this->payload()['accessToken'];
    }

    private function get(string $path, string $token): void
    {
        $this->client->request('GET', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function put(string $path, string $token, array $body): void
    {
        $this->client->request('PUT', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
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
