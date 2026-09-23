<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\Entity\AccountActivationToken;
use LectoresBeta\User\Account\Domain\Repository\AccountActivationTokenRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\AccountActivationTokenId;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * El ciclo de vida de una obra (`FEAT-WRK-016`).
 *
 * Publicar es lo que convierte todo lo anterior en algo que existe para
 * alguien más, así que lo que más importa aquí no es que funcione el camino
 * feliz: es que **un borrador no se escape**.
 */
final class PublishWorkTest extends WebTestCase
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

    public function testPublishingAWorkWithAChapterAnnouncesIt(): void
    {
        $token = $this->author();
        $workId = $this->workWithAChapter($token);

        $this->changeStatus($workId, $token, 'PUBLISHED');

        self::assertResponseIsSuccessful();
        self::assertSame('PUBLISHED', $this->payload()['status']);
        self::assertSame(WorkStatus::PUBLISHED, $this->workOf($workId)->status());
        self::assertSame(['WorkPublished'], $this->published());
    }

    /**
     * La regla de que una obra tiene contenido muerde **al publicar**, no al
     * crear: una obra nace vacía a propósito, porque los capítulos se añaden
     * uno a uno. Lo que no puede es ponerse nada delante de un lector.
     */
    public function testAWorkWithNoChaptersCannotBePublished(): void
    {
        $token = $this->author();
        $workId = $this->createWork($token);

        $this->changeStatus($workId, $token, 'PUBLISHED');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('WORK_HAS_NO_CHAPTERS', $this->payload()['code']);
        self::assertSame(WorkStatus::DRAFT, $this->workOf($workId)->status());
    }

    /**
     * Publicar y abrir a corrección son **dos pasos**. Saltárselos escondería
     * una publicación dentro de otra acción, y el autor empezaría a gastar
     * créditos sobre un texto que nadie ha visto aún.
     */
    public function testADraftCannotJumpStraightIntoCorrection(): void
    {
        $token = $this->author();
        $workId = $this->workWithAChapter($token);

        $this->changeStatus($workId, $token, 'IN_CORRECTION');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ILLEGAL_WORK_TRANSITION', $this->payload()['code']);
    }

    public function testOpeningAndClosingCorrectionAnnounceThemselves(): void
    {
        $token = $this->author();
        $workId = $this->workWithAChapter($token);

        $this->changeStatus($workId, $token, 'PUBLISHED');
        self::assertSame(['WorkPublished'], $this->published());

        $this->changeStatus($workId, $token, 'IN_CORRECTION');
        self::assertResponseIsSuccessful();
        self::assertSame(['WorkOpenedForCorrection'], $this->published());

        $this->changeStatus($workId, $token, 'PUBLISHED');
        self::assertResponseIsSuccessful();
        self::assertSame(['WorkClosedForCorrection'], $this->published());
        self::assertSame(WorkStatus::PUBLISHED, $this->workOf($workId)->status());
    }

    /**
     * Sigue siendo `W-10`. Hasta que se decida, la respuesta honesta es «no»:
     * puede que alguien ya lo haya leído.
     */
    public function testAPublishedWorkCannotGoBackToBeingADraft(): void
    {
        $token = $this->author();
        $workId = $this->workWithAChapter($token);
        $this->changeStatus($workId, $token, 'PUBLISHED');

        $this->changeStatus($workId, $token, 'DRAFT');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ILLEGAL_WORK_TRANSITION', $this->payload()['code']);
        self::assertSame(WorkStatus::PUBLISHED, $this->workOf($workId)->status());
    }

    public function testAnInventedStatusIsRefused(): void
    {
        $token = $this->author();
        $workId = $this->workWithAChapter($token);

        $this->changeStatus($workId, $token, 'PUBLICADA');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ILLEGAL_WORK_TRANSITION', $this->payload()['code']);
    }

    /**
     * **Lo que más caro sale si falla.** Un borrador es obra inédita, y que
     * alguien ajeno pueda publicarlo sería exponerla sin el consentimiento de
     * quien la escribió.
     */
    public function testNobodyElseCanPublishYourWork(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->workWithAChapter($ownerToken);

        $intruderToken = $this->author('intrusa');
        $this->changeStatus($workId, $intruderToken, 'PUBLISHED');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
        self::assertSame(WorkStatus::DRAFT, $this->workOf($workId)->status());
    }

    public function testAnUnactivatedAccountCannotChangeTheStatus(): void
    {
        $ownerToken = $this->author();
        $workId = $this->workWithAChapter($ownerToken);

        $unactivated = $this->signUpAndSignIn('sinactivar');
        $this->changeStatus($workId, $unactivated, 'PUBLISHED');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    private function workWithAChapter(string $token): string
    {
        $workId = $this->createWork($token);

        $this->post(\sprintf('/api/v1/works/%s/chapters', $workId), $token, [
            'content' => '<p>Era una noche oscura y tormentosa</p>',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $workId;
    }

    private function createWork(string $token): string
    {
        $this->post('/api/v1/works', $token, ['title' => 'La ciudad de los pájaros']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['workId'];
    }

    private function changeStatus(string $workId, string $token, string $status): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/status', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['status' => $status], \JSON_THROW_ON_ERROR));
    }

    /**
     * Los hechos publicados por la última petición. Por petición y no
     * acumulados: Symfony reinicia el transporte en memoria al terminar cada
     * una.
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

    private function author(string $local = 'autora'): string
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

    private function workOf(string $workId): \LectoresBeta\Work\Manuscript\Domain\Entity\Work
    {
        /** @var WorkRepository $works */
        $works = self::getContainer()->get(WorkRepository::class);
        $work = $works->ofId(WorkId::fromString($workId));
        self::assertNotNull($work);

        return $work;
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
