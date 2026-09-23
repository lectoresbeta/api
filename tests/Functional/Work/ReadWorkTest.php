<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\User\Account\Domain\Entity\AccountActivationToken;
use LectoresBeta\User\Account\Domain\Repository\AccountActivationTokenRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\AccountActivationTokenId;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leer una obra (`FEAT-WRK-004`).
 *
 * Lo que más importa aquí no es que el camino feliz funcione: es que **todas
 * las negativas sean la misma negativa**. Cualquier diferencia observable
 * entre «no existe» y «existe y no puedes» es información sobre obra inédita,
 * que es justo el activo que esta plataforma custodia.
 */
final class ReadWorkTest extends WebTestCase
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

    public function testTheAuthorReadsTheirOwnDraft(): void
    {
        $token = $this->author('autora');
        $workId = $this->workWithAChapter($token);

        $this->get('/api/v1/works/'.$workId, $token);

        self::assertResponseIsSuccessful();
        self::assertSame('DRAFT', $this->payload()['status']);
        self::assertCount(1, (array) $this->payload()['chapters']);
    }

    /**
     * **La propiedad central.** Un borrador ajeno y una obra que no existe
     * responden igual, byte a byte.
     */
    public function testAStrangersDraftIsIndistinguishableFromAWorkThatDoesNotExist(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->workWithAChapter($ownerToken);

        $strangerToken = $this->author('curiosa');

        $this->get('/api/v1/works/'.$workId, $strangerToken);
        $someoneElsesDraft = $this->client->getResponse();

        $this->get('/api/v1/works/01a0cfd8-0000-7000-8000-000000000404', $strangerToken);
        $noSuchWork = $this->client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $someoneElsesDraft->getStatusCode());
        self::assertSame($someoneElsesDraft->getContent(), $noSuchWork->getContent());
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
    }

    public function testAPublishedPublicWorkIsReadableByAnybodySignedIn(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->workWithAChapter($ownerToken);
        $this->openTo($workId, $ownerToken, 'PUBLIC');
        $this->publish($workId, $ownerToken);

        $readerToken = $this->author('lectora');
        $this->get('/api/v1/works/'.$workId, $readerToken);

        self::assertResponseIsSuccessful();
        self::assertSame('PUBLISHED', $this->payload()['status']);
    }

    /**
     * Una obra nace `ON_REQUEST`, y publicarla **no la abre**: quién puede
     * acercarse es una decisión aparte, y su valor por defecto es el que
     * menos expone (`FEAT-WRK-007`).
     *
     * Hasta que exista `Reading`, «acceso concedido» no existe, así que una
     * obra `ON_REQUEST` publicada solo la lee su autor. Es el lado seguro.
     */
    public function testPublishingDoesNotOpenAWorkThatIsOnRequest(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->workWithAChapter($ownerToken);
        $this->publish($workId, $ownerToken);

        $readerToken = $this->author('lectora');
        $this->get('/api/v1/works/'.$workId, $readerToken);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
    }

    public function testAnUnknownAccessModeIsRefused(): void
    {
        $token = $this->author('autora');
        $workId = $this->workWithAChapter($token);

        $this->client->request('PUT', \sprintf('/api/v1/works/%s/access-mode', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['accessMode' => 'ABIERTA'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_ACCESS_MODE', $this->payload()['code']);
    }

    public function testNobodyElseChangesYourAccessMode(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->workWithAChapter($ownerToken);

        $strangerToken = $this->author('curiosa');
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/access-mode', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$strangerToken,
        ], content: json_encode(['accessMode' => 'PUBLIC'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Leer no es escribir: la barrera de `FEAT-USR-025` no alcanza aquí.
     */
    public function testAnUnactivatedAccountCanRead(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->workWithAChapter($ownerToken);
        $this->openTo($workId, $ownerToken, 'PUBLIC');
        $this->publish($workId, $ownerToken);

        $unactivated = $this->signUpAndSignIn('sinactivar');
        $this->get('/api/v1/works/'.$workId, $unactivated);

        self::assertResponseIsSuccessful();
    }

    public function testReadingRequiresASession(): void
    {
        $this->client->request('GET', '/api/v1/works/01a0cfd8-0000-7000-8000-000000000404');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testTheChapterTextIsServedExactlyAsStored(): void
    {
        $token = $this->author('autora');
        $workId = $this->workWithAChapter($token, '<p>Era una <strong>noche</strong> oscura</p>');

        $this->get('/api/v1/works/'.$workId, $token);
        $chapters = (array) $this->payload()['chapters'];
        $chapterId = (string) ((array) $chapters[0])['chapterId'];

        $this->get('/api/v1/chapters/'.$chapterId, $token);

        self::assertResponseIsSuccessful();
        self::assertSame('<p>Era una <strong>noche</strong> oscura</p>', $this->payload()['content']);
        self::assertSame(4, $this->payload()['wordCount']);
    }

    /**
     * Un capítulo nunca se autoriza por su cuenta: que alguien pueda leerlo
     * es una propiedad de la obra a la que pertenece.
     */
    public function testAChapterOfAStrangersDraftIsNotReadable(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->workWithAChapter($ownerToken);

        $this->get('/api/v1/works/'.$workId, $ownerToken);
        $chapters = (array) $this->payload()['chapters'];
        $chapterId = (string) ((array) $chapters[0])['chapterId'];

        $strangerToken = $this->author('curiosa');
        $this->get('/api/v1/chapters/'.$chapterId, $strangerToken);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('CHAPTER_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * `RN-10`: ni el correo ni la fecha de nacimiento del autor tienen nada
     * que hacer aquí.
     */
    public function testTheResponseCarriesNothingPersonalAboutTheAuthor(): void
    {
        $token = $this->author('autora');
        $workId = $this->workWithAChapter($token);

        $this->get('/api/v1/works/'.$workId, $token);

        $body = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('@ejemplo.com', $body);
        self::assertStringNotContainsString('birthDate', $body);
    }

    private function openTo(string $workId, string $token, string $mode): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/access-mode', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['accessMode' => $mode], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
    }

    private function publish(string $workId, string $token): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/status', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['status' => 'PUBLISHED'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
    }

    private function workWithAChapter(string $token, string $content = '<p>Era una noche oscura</p>'): string
    {
        $this->post('/api/v1/works', $token, ['title' => 'La ciudad de los pájaros']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $workId = (string) $this->payload()['workId'];

        $this->post(\sprintf('/api/v1/works/%s/chapters', $workId), $token, ['content' => $content]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $workId;
    }

    private function get(string $path, string $token): void
    {
        $this->client->request('GET', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, string $token, array $body): void
    {
        $this->client->request('POST', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function address(string $local): string
    {
        return \sprintf('%s+%s@ejemplo.com', $local, $this->run);
    }

    private function signUpAndSignIn(string $local): string
    {
        $email = $this->address($local);

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

    private function author(string $local): string
    {
        $token = $this->signUpAndSignIn($local);

        $user = $this->users()->ofEmail(Email::fromString($this->address($local)));
        self::assertNotNull($user);

        /** @var SecureTokenFactory $factory */
        $factory = self::getContainer()->get(SecureTokenFactory::class);
        $secret = $factory->create();

        /** @var AccountActivationTokenRepository $tokens */
        $tokens = self::getContainer()->get(AccountActivationTokenRepository::class);
        $tokens->save(new AccountActivationToken(
            AccountActivationTokenId::generate(),
            $user->id(),
            $secret->hash,
            new \DateTimeImmutable(),
            new \DateTimeImmutable('+2 days'),
        ));

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->flush();

        $this->client->request('POST', '/api/v1/auth/activate', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $secret->plain],
            \JSON_THROW_ON_ERROR,
        ));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $token;
    }

    private function users(): UserRepository
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);

        return $users;
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
