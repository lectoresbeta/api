<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Health;

use LectoresBeta\Shared\Application\Health\CheckHealth;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Health\CheckResult;
use LectoresBeta\Shared\Domain\Health\HealthCheck;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * El endpoint de salud, de extremo a extremo: enrutado, seguridad, formato de
 * la respuesta y código HTTP.
 *
 * Lo que de verdad se comprueba aquí es que **responde sin autenticación**.
 * Es la única pieza del sistema de la que eso se espera, y la que más caro
 * sale si algún día deja de cumplirse: una sonda que empieza a recibir 401
 * declara el servicio caído estando perfectamente sano.
 */
final class HealthEndpointTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testLivenessAnswersWithoutAuthentication(): void
    {
        $this->client->request('GET', '/health/live');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['status' => 'up'], $this->payload());
    }

    public function testReadinessAnswersWithoutAuthentication(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $payload = $this->payload();

        self::assertSame('up', $payload['status']);
        self::assertArrayHasKey('checkedAt', $payload);
        self::assertArrayHasKey('database', $payload['checks']);
        self::assertArrayHasKey('message_broker', $payload['checks']);
    }

    public function testItReachesTheRealDatabase(): void
    {
        $this->client->request('GET', '/health');

        self::assertSame('up', $this->payload()['checks']['database']['status']);
    }

    /**
     * En test el transporte es in-memory, así que no hay RabbitMQ al que
     * llegar. Decir «caído» ataría la suite a tener un broker levantado;
     * decir «bien» sería mentira.
     */
    public function testTheBrokerCheckIsSkippedWhenTheTransportIsNotAmqp(): void
    {
        $this->client->request('GET', '/health');

        $broker = $this->payload()['checks']['message_broker'];

        self::assertSame('skipped', $broker['status']);
        self::assertStringContainsString('in-memory', $broker['detail']);
    }

    /**
     * Una respuesta de salud cacheada es peor que no tener ninguna: informa
     * del estado de algún momento anterior con total seguridad.
     */
    public function testTheResponseIsNeverCached(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }

    public function testOnlyGetIsAllowed(): void
    {
        $this->client->request('POST', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * Lo que justifica el endpoint entero: una dependencia caída tiene que
     * salir por 503, no por 200 con un detalle escondido en el cuerpo. Quien
     * lo consulta suele mirar solo el código.
     */
    public function testABrokenDependencyAnswersWithServiceUnavailable(): void
    {
        $this->client->disableReboot();

        $clock = self::getContainer()->get(Clock::class);
        \assert($clock instanceof Clock);

        self::getContainer()->set(CheckHealth::class, new CheckHealth(
            [$this->failingCheck('database')],
            $clock,
        ));

        $this->client->request('GET', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);

        $payload = $this->payload();

        self::assertSame('down', $payload['status']);
        self::assertSame('down', $payload['checks']['database']['status']);
    }

    /**
     * El cuerpo no dice **por qué** ha fallado. El endpoint es público, y
     * «conexión rechazada en 10.0.3.7:5432» le regala la topología del
     * sistema a cualquiera.
     */
    public function testAFailureRevealsNothingAboutTheInfrastructure(): void
    {
        $this->client->disableReboot();

        $clock = self::getContainer()->get(Clock::class);
        \assert($clock instanceof Clock);

        self::getContainer()->set(CheckHealth::class, new CheckHealth(
            [$this->failingCheck('database')],
            $clock,
        ));

        $this->client->request('GET', '/health');

        $body = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('5432', $body);
        self::assertStringNotContainsString('postgres', $body);
        self::assertStringNotContainsString('Exception', $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function failingCheck(string $name): HealthCheck
    {
        return new class($name) implements HealthCheck {
            public function __construct(private readonly string $name)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function run(): CheckResult
            {
                return CheckResult::down($this->name, 42.0);
            }
        };
    }
}
