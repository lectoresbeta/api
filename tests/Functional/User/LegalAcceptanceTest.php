<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aceptar las condiciones y la política de privacidad (`FEAT-USR-024`).
 *
 * **La diferencia entre una prueba y un booleano disfrazado.** Guardar
 * `accepted: true` no demuestra nada si el texto cambia después, y guardar
 * una versión cualquiera que mande el cliente tampoco: lo que queda entonces
 * en la base de datos parece una prueba y no lo es.
 */
final class LegalAcceptanceTest extends WebTestCase
{
    private const PASSWORD = 'Valida1!';

    private KernelBrowser $client;

    private string $run;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(5));
    }

    /**
     * Sin sesión, porque hace falta antes de tenerla.
     */
    public function testTheDocumentsInForceCanBeReadWithoutASession(): void
    {
        $this->client->request('GET', '/api/v1/legal/documents');

        self::assertResponseIsSuccessful();

        $tipos = array_column($this->payload()['documents'], 'type');
        self::assertContains('TERMS_OF_USE', $tipos);
        self::assertContains('PRIVACY_POLICY', $tipos);

        foreach ($this->payload()['documents'] as $documento) {
            self::assertNotEmpty($documento['version'], 'Cada documento dice qué versión es.');
            self::assertNotEmpty($documento['url']);
        }
    }

    /**
     * `RN-2`: no basta con que venga una versión, tiene que ser **la
     * vigente**.
     */
    public function testAnOutdatedVersionIsRefusedAndSaysWhichIsCurrent(): void
    {
        $vigente = $this->versionOf('TERMS_OF_USE');

        $this->register('desfasada', terms: '2020-01-01', privacy: $this->versionOf('PRIVACY_POLICY'));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // El cuerpo se lee una sola vez: cada petición pisa la respuesta.
        $problema = $this->payload();

        self::assertSame('LEGAL_VERSION_OUTDATED', $problema['code']);
        self::assertSame($vigente, $problema['TERMS_OF_USE'], 'Y dice cuál es la vigente, para recargar el texto.');
    }

    /**
     * `RN-1`: es una validación de servidor y no solo del formulario.
     */
    public function testRegisteringWithoutAcceptingIsRefused(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $this->address('sinaceptar'),
            'password' => self::PASSWORD,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * `RN-5`: los dos documentos se comprueban por separado aunque la casilla
     * sea una sola. Cambian por motivos distintos y con frecuencias
     * distintas.
     */
    public function testEachDocumentIsCheckedOnItsOwn(): void
    {
        $this->register('mitad', terms: $this->versionOf('TERMS_OF_USE'), privacy: '2019-05-05');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('LEGAL_VERSION_OUTDATED', $this->payload()['code']);
    }

    /**
     * `RN-6`: el derecho a ver la prueba. Con su versión y su fecha, que es
     * lo que la convierte en prueba.
     */
    public function testWhatIAcceptedCanBeConsulted(): void
    {
        $email = $this->address('curiosa');
        $this->register('curiosa', $this->versionOf('TERMS_OF_USE'), $this->versionOf('PRIVACY_POLICY'));
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $token = $this->logIn($email);

        $this->client->request('GET', '/api/v1/me/legal-acceptances', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $aceptaciones = $this->payload()['acceptances'];

        self::assertCount(2, $aceptaciones);

        foreach ($aceptaciones as $aceptacion) {
            self::assertNotEmpty($aceptacion['version']);
            self::assertNotEmpty($aceptacion['acceptedAt']);
        }

        self::assertStringNotContainsString('ipAddress', json_encode($aceptaciones, \JSON_THROW_ON_ERROR));
    }

    public function testWithoutASessionThereAreNoAcceptancesToSee(): void
    {
        $this->client->request('GET', '/api/v1/me/legal-acceptances');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function versionOf(string $type): string
    {
        $this->client->request('GET', '/api/v1/legal/documents');
        self::assertResponseIsSuccessful();

        foreach ($this->payload()['documents'] as $documento) {
            if ($documento['type'] === $type) {
                return (string) $documento['version'];
            }
        }

        self::fail(\sprintf('No hay ningún %s vigente.', $type));
    }

    private function register(string $local, string $terms, string $privacy): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $this->address($local),
            'password' => self::PASSWORD,
            'acceptedLegalVersions' => ['termsOfUse' => $terms, 'privacyPolicy' => $privacy],
        ], \JSON_THROW_ON_ERROR));
    }

    private function logIn(string $email): string
    {
        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => \sprintf('198.51.%d.%d', random_int(0, 255), random_int(1, 254)),
        ], content: json_encode(['email' => $email, 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return (string) $this->payload()['accessToken'];
    }

    private function address(string $local): string
    {
        return \sprintf('%s+%s@ejemplo.com', $local, $this->run);
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
