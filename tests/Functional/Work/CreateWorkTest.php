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
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Crear una obra y añadirle capítulos (`FEAT-WRK-001`).
 *
 * Es el primer endpoint de escritura **no exento**, así que aquí se comprueba
 * por fin la mitad de
 * [`FEAT-USR-025`](../../../docs/features/user/FEAT-USR-025-block-writes-until-activation.md)
 * que hasta ahora no comprobaba nadie: que la barrera **bloquea**, y no solo
 * que deja pasar lo que debe.
 */
final class CreateWorkTest extends WebTestCase
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

    /**
     * **El test que faltaba.** Una cuenta sin activar no escribe, y se le
     * dice exactamente qué le pasa para que la interfaz pueda ofrecerle el
     * reenvío del correo.
     */
    public function testAnUnactivatedAccountCannotCreateAWork(): void
    {
        $token = $this->signUpAndSignIn('sinactivar');

        $this->post('/api/v1/works', $token, ['title' => 'Una obra']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    public function testAnActivatedAccountCreatesAWorkInDraft(): void
    {
        $token = $this->signUpActivateAndSignIn('autora');

        $this->post('/api/v1/works', $token, ['title' => 'La ciudad de los pájaros']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $work = $this->workOf((string) $this->payload()['workId']);

        self::assertSame(WorkStatus::DRAFT, $work->status(), 'Nacer visible expondría obra inédita por un descuido.');
        self::assertSame(0, $work->wordCount());
    }

    /**
     * Aceptar un `authorId` del cuerpo sería una vía directa a crear obras en
     * nombre de otro.
     */
    public function testTheAuthorIsAlwaysTheCallerEvenIfTheBodySaysOtherwise(): void
    {
        $token = $this->signUpActivateAndSignIn('autora');
        $me = $this->users()->ofEmail(Email::fromString($this->address('autora')));
        self::assertNotNull($me);

        $this->post('/api/v1/works', $token, [
            'title' => 'Una obra',
            'authorId' => '0199c7f2-0000-7000-8000-000000000999',
        ]);

        $work = $this->workOf((string) $this->payload()['workId']);

        self::assertSame($me->id()->value(), $work->authorId()->value());
    }

    public function testAWorkWithoutATitleIsRefused(): void
    {
        $token = $this->signUpActivateAndSignIn('autora');

        $this->post('/api/v1/works', $token, ['title' => '  ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddingAChapterCountsItsWordsIntoTheWork(): void
    {
        $token = $this->signUpActivateAndSignIn('autora');
        $workId = $this->createWork($token);

        $this->post(\sprintf('/api/v1/works/%s/chapters', $workId), $token, [
            'title' => 'Capítulo primero',
            'content' => '<p>Uno dos tres</p><p>cuatro cinco</p>',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $work = $this->workOf($workId);

        self::assertSame(5, $work->wordCount());
        self::assertSame(1, $work->chapterCount());
    }

    /**
     * Sin espacio entre bloques, `<p>uno</p><p>dos</p>` se contaría como una
     * sola palabra, y de ese número depende el precio de toda corrección.
     */
    public function testWordsAreNotGluedTogetherAcrossParagraphs(): void
    {
        $token = $this->signUpActivateAndSignIn('autora');
        $workId = $this->createWork($token);

        $this->post(\sprintf('/api/v1/works/%s/chapters', $workId), $token, [
            'content' => '<p>uno</p><p>dos</p>',
        ]);

        self::assertSame(2, $this->workOf($workId)->wordCount());
    }

    /**
     * Se sanea **al escribir**: no se guarda nada que no se estuviera
     * dispuesto a servir.
     */
    public function testDangerousMarkupNeverReachesStorage(): void
    {
        $token = $this->signUpActivateAndSignIn('autora');
        $workId = $this->createWork($token);

        $this->post(\sprintf('/api/v1/works/%s/chapters', $workId), $token, [
            'content' => '<p>Hola <script>alert(1)</script><a href="http://spam.example">pincha aquí</a> mundo</p>',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $stored = $this->chapters()->ofWork(WorkId::fromString($workId))[0]->content();

        self::assertStringNotContainsString('<script', $stored->html);
        self::assertStringNotContainsString('alert(1)', $stored->html);
        self::assertStringNotContainsString('<a ', $stored->html);
        self::assertStringNotContainsString('spam.example', $stored->html);

        // Y el texto sobrevive: tirar las palabras con el marcado sería una
        // pésima bienvenida para quien pega desde Word.
        self::assertStringContainsString('Hola', $stored->text);
        self::assertStringContainsString('mundo', $stored->text);
        self::assertStringContainsString('pincha aquí', $stored->text);
    }

    public function testAChapterThatIsAllDisallowedMarkupIsRefused(): void
    {
        $token = $this->signUpActivateAndSignIn('autora');
        $workId = $this->createWork($token);

        $this->post(\sprintf('/api/v1/works/%s/chapters', $workId), $token, [
            'content' => '<img src="x"><br>',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('EMPTY_CHAPTER', $this->payload()['code']);
    }

    /**
     * Que una obra inédita exista no se le confirma a quien no es su autor:
     * «no es tuya» y «no existe» son la misma respuesta.
     */
    public function testAnotherAuthorsWorkIsIndistinguishableFromOneThatDoesNotExist(): void
    {
        $ownerToken = $this->signUpActivateAndSignIn('autora');
        $workId = $this->createWork($ownerToken);

        $intruderToken = $this->signUpActivateAndSignIn('intrusa');

        $this->post(\sprintf('/api/v1/works/%s/chapters', $workId), $intruderToken, ['content' => '<p>mío ahora</p>']);
        $notMine = $this->client->getResponse();

        $this->post('/api/v1/works/0199c7f2-0000-7000-8000-000000000404/chapters', $intruderToken, ['content' => '<p>hola</p>']);
        $notThere = $this->client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $notMine->getStatusCode());
        self::assertSame($notMine->getContent(), $notThere->getContent());
    }

    public function testCreatingAWorkRequiresASession(): void
    {
        $this->client->request('POST', '/api/v1/works', server: ['CONTENT_TYPE' => 'application/json'], content: '{"title":"Una obra"}');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function createWork(string $token): string
    {
        $this->post('/api/v1/works', $token, ['title' => 'La ciudad de los pájaros']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['workId'];
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

    private function signUpActivateAndSignIn(string $local): string
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

    private function workOf(string $workId): Work
    {
        $work = $this->works()->ofId(WorkId::fromString($workId));
        self::assertNotNull($work);

        return $work;
    }

    private function works(): WorkRepository
    {
        /** @var WorkRepository $works */
        $works = self::getContainer()->get(WorkRepository::class);

        return $works;
    }

    private function chapters(): ChapterRepository
    {
        /** @var ChapterRepository $chapters */
        $chapters = self::getContainer()->get(ChapterRepository::class);

        return $chapters;
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
