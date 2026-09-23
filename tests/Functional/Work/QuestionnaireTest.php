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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * El cuestionario (`FEAT-WRK-014`).
 *
 * No es un constructor de formularios: es **el instrumento con el que el
 * autor fija el precio de su propia corrección**. Por eso casi todo lo que se
 * comprueba aquí son límites, y no formatos.
 */
final class QuestionnaireTest extends WebTestCase
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

    public function testAWorkWithoutAQuestionnaireAnswersWithVersionZero(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->get(\sprintf('/api/v1/works/%s/questionnaire', $workId), $token);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->payload()['version']);
        self::assertSame([], $this->payload()['questions']);
    }

    public function testSavingAQuestionnaireAnnouncesWhatCreditsNeeds(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->save($workId, $token, [
            ['statement' => '¿Te ha convencido la conexión entre Ryn y su misión?', 'minWords' => 100],
            ['statement' => '¿Qué te ha parecido el desenlace?', 'minWords' => 150, 'scope' => 'LAST_CHAPTER'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->payload()['version']);

        $event = $this->lastPublished();

        self::assertSame('QuestionnaireUpdated', $event->eventName());

        $payload = $event->payload();

        self::assertSame(2, $payload['questionCount']);
        self::assertSame(250, $payload['requiredWords'], 'El último capítulo responde a todas.');
        self::assertSame(100, $payload['requiredWordsForEveryChapter'], 'Los demás no pagan la del desenlace.');

        // El enunciado es contenido del autor y no circula por la cola.
        self::assertStringNotContainsString('Ryn', json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * `RN-4`: editar crea una versión nueva. Sin ello, una corrección ya
     * entregada quedaría huérfana — respuestas sin las preguntas que las
     * motivaron.
     */
    public function testEditingCreatesANewVersion(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->save($workId, $token, [['statement' => 'Primera', 'minWords' => 50]]);
        self::assertSame(1, $this->payload()['version']);

        $this->save($workId, $token, [['statement' => 'Segunda', 'minWords' => 60]]);
        self::assertSame(2, $this->payload()['version']);

        $this->get(\sprintf('/api/v1/works/%s/questionnaire', $workId), $token);
        self::assertSame(2, $this->payload()['version']);
        self::assertSame(60, $this->payload()['requiredWords']);
    }

    /**
     * Dos pestañas editando lo mismo se pisarían **en silencio**, y como esto
     * fija el precio de cada corrección, perder la mitad sin enterarse no es
     * aceptable.
     */
    public function testASecondTabEditingTheSameQuestionnaireIsRefused(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->save($workId, $token, [['statement' => 'Primera', 'minWords' => 50]]);
        $this->save($workId, $token, [['statement' => 'Segunda', 'minWords' => 50]], ifMatch: 1);

        self::assertResponseIsSuccessful();

        // La otra pestaña seguía creyendo que la versión vigente era la 1.
        $this->save($workId, $token, [['statement' => 'Tercera', 'minWords' => 50]], ifMatch: 1);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('STALE_VERSION', $this->payload()['code']);
    }

    public function testAQuestionnaireNeedsAtLeastOneQuestion(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->save($workId, $token, []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('QUESTIONNAIRE_WITHOUT_QUESTIONS', $this->payload()['code']);
    }

    /**
     * `C-14`, resuelta: declarar el mínimo es obligatorio porque **es el
     * precio**. Sin él, el autor pediría trabajo sin decir cuánto.
     */
    public function testEveryQuestionMustSayHowManyWordsItAsksFor(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->save($workId, $token, [['statement' => 'Cuéntame qué te ha parecido']]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('MISSING_MINIMUM_WORDS', $this->payload()['code']);
    }

    /**
     * Más allá de 2.000 palabras el término de escritura alcanza el tope de
     * 20 créditos: el autor no paga más y quien corrige escribe más.
     */
    public function testAQuestionnaireCannotDemandMoreWorkThanItPaysFor(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->save($workId, $token, [
            ['statement' => 'Una', 'minWords' => 1500],
            ['statement' => 'Otra', 'minWords' => 600],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('TOO_MANY_REQUIRED_WORDS', $this->payload()['code']);
    }

    /**
     * Si todas las preguntas fueran del último capítulo, los demás no
     * tendrían nada que responder y el autor pagaría por ellos igualmente.
     */
    public function testAQuestionnaireOnlyForTheLastChapterIsRefused(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->save($workId, $token, [
            ['statement' => '¿Y el final?', 'minWords' => 100, 'scope' => 'LAST_CHAPTER'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('QUESTIONNAIRE_ONLY_FOR_LAST_CHAPTER', $this->payload()['code']);
    }

    public function testAMinimumLongerThanItsMaximumIsRefused(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->save($workId, $token, [['statement' => 'Una', 'minWords' => 200, 'maxWords' => 100]]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_WORD_RANGE', $this->payload()['code']);
    }

    public function testNobodyElseConfiguresYourQuestionnaire(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->createWork($ownerToken);

        $strangerToken = $this->author('curiosa');
        $this->save($workId, $strangerToken, [['statement' => 'Mía ahora', 'minWords' => 50]]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * El cuestionario describe una obra inédita tan bien como sus capítulos:
     * las preguntas nombran personajes y giros. Guardar el texto y dejar las
     * preguntas abiertas sería guardar la puerta y dejar la ventana.
     */
    public function testAStrangerCannotReadTheQuestionnaireOfADraft(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->createWork($ownerToken);
        $this->save($workId, $ownerToken, [['statement' => 'Sobre Ryn', 'minWords' => 50]]);

        $strangerToken = $this->author('curiosa');
        $this->get(\sprintf('/api/v1/works/%s/questionnaire', $workId), $strangerToken);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testConfiguringRequiresAnActivatedAccount(): void
    {
        $ownerToken = $this->author('autora');
        $workId = $this->createWork($ownerToken);

        $unactivated = $this->signUpAndSignIn('sinactivar');
        $this->save($workId, $unactivated, [['statement' => 'Una', 'minWords' => 50]]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    /**
     * @param list<array<string, mixed>> $questions
     */
    private function save(string $workId, string $token, array $questions, ?int $ifMatch = null): void
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];

        if (null !== $ifMatch) {
            $server['HTTP_IF_MATCH'] = \sprintf('"%d"', $ifMatch);
        }

        $this->client->request('PUT', \sprintf('/api/v1/works/%s/questionnaire', $workId), server: $server, content: json_encode(
            ['questions' => $questions],
            \JSON_THROW_ON_ERROR,
        ));
    }

    private function lastPublished(): IntegrationEvent
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.integration');
        $sent = array_values($transport->getSent());

        self::assertNotEmpty($sent);

        /** @var Envelope $last */
        $last = end($sent);
        $event = $last->getMessage();
        self::assertInstanceOf(IntegrationEvent::class, $event);

        return $event;
    }

    private function createWork(string $token): string
    {
        $this->client->request('POST', '/api/v1/works', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['title' => 'La ciudad de los pájaros'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['workId'];
    }

    private function get(string $path, string $token): void
    {
        $this->client->request('GET', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
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
