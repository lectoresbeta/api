<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Shared\Infrastructure\Messenger\IntegrationEventSerializer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Mime\Email as MimeEmail;

/**
 * «¿No te ha llegado? Reenviar enlace» (`FEAT-USR-021`).
 *
 * Lo que de verdad se prueba aquí son **dos cosas que no se ven en la
 * pantalla**: que los tres desenlaces posibles son indistinguibles desde
 * fuera —si no, el formulario diría quién tiene cuenta— y que los límites
 * de frecuencia existen, porque un endpoint público que dispara correo con
 * el dominio de la plataforma en el remitente es un amplificador de spam.
 *
 * Cada caso usa una dirección distinta: los contadores viven en caché y
 * tienen la dirección por clave, así que compartirla mezclaría los límites de
 * un test con los de otro.
 */
final class ResendActivationEmailTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $run;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(5));
    }

    /**
     * `RN-1`: solo vale el enlace del último correo. Dos vivos a la vez
     * doblarían la ventana en la que uno filtrado sigue abriendo la cuenta.
     */
    public function testAResendInvalidatesThePreviousLink(): void
    {
        $email = $this->register('primero');
        $primerToken = $this->tokenOfTheLastEmail();

        $this->resend($email);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumePendingEvents();

        $segundoToken = $this->tokenOfTheLastEmail();
        self::assertNotSame($primerToken, $segundoToken);

        $this->activate($primerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'El primer enlace deja de valer.');
        self::assertSame('INVALID_ACTIVATION_TOKEN', $this->payload()['code']);

        $this->activate($segundoToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT, 'Y el último funciona.');
    }

    /**
     * `RN-6`: la respuesta no espera al correo. Atarla al proveedor de email
     * haría que una caída suya se viera como una caída del alta.
     */
    public function testTheResponseDoesNotWaitForTheEmail(): void
    {
        $email = $this->register('asincrono');

        $this->resend($email);

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertCount(0, self::getMailerMessages(), 'La petición termina sin haber mandado nada.');

        $this->consumePendingEvents();
        self::assertCount(1, self::getMailerMessages(), 'El correo sale después, por la cola.');
    }

    /**
     * `RN-4`: **la prueba que importa.** Un correo sin registrar responde
     * exactamente lo mismo que uno registrado. Si no, este formulario sería
     * un comprobador de qué direcciones tienen cuenta.
     */
    public function testAnUnknownAddressAnswersExactlyTheSame(): void
    {
        $registrado = $this->register('registrado');

        $this->resend($registrado);
        $conCuenta = $this->client->getResponse();
        $this->consumePendingEvents();
        $correos = self::getMailerMessages();

        $this->resend(\sprintf('nadie+%s@ejemplo.com', $this->run));
        $sinCuenta = $this->client->getResponse();
        $this->consumePendingEvents();

        // Lo que ve quien pregunta: exactamente lo mismo.
        self::assertSame($conCuenta->getStatusCode(), $sinCuenta->getStatusCode());
        self::assertSame($conCuenta->getContent(), $sinCuenta->getContent());

        // La diferencia está donde no se ve.
        self::assertCount(1, $correos);
        self::assertInstanceOf(MimeEmail::class, $correos[0]);
        self::assertSame($registrado, $correos[0]->getTo()[0]->getAddress());
        self::assertCount(0, self::getMailerMessages(), 'Nada para quien no existe.');
    }

    /**
     * `RN-5`: una cuenta ya activa no genera correo, y tampoco lo dice. Y por
     * lo mismo un correo mal formado responde `202`: un `422` ahí ya sería
     * una diferencia observable entre direcciones.
     */
    public function testAnActiveAccountSendsNothingAndDoesNotSaySo(): void
    {
        $email = $this->register('activada');
        $this->activate($this->tokenOfTheLastEmail());
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->resend($email);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->resend('esto-no-es-un-correo');
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->consumePendingEvents();
        self::assertCount(0, self::getMailerMessages(), 'No sale ningún correo.');
    }

    /**
     * `RN-2` y `RN-3`, y **por qué son dos códigos y no uno**: significan
     * cosas opuestas para quien está delante. «Espera un minuto» deja la
     * puerta abierta; «vuelve mañana» no. Con un solo código la pantalla
     * tendría que enseñar siempre el mensaje pesimista.
     */
    public function testTheTwoLimitsAreDistinguishableAndCarryRetryAfter(): void
    {
        $email = $this->register('insistente');

        $this->resend($email);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->resend($email);
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('RESEND_TOO_SOON', $this->payload()['code']);
        self::assertGreaterThan(0, $this->payload()['retryAfterSeconds']);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));

        // Hasta agotar el tope del periodo. Los contadores se consumen en
        // cada petición —también en las rechazadas—, que es lo que impide
        // esquivar el tope espaciando los intentos.
        for ($intento = 0; $intento < 4; ++$intento) {
            $this->resend($email);
        }

        self::assertSame('RESEND_LIMIT_REACHED', $this->payload()['code']);
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    private function register(string $local): string
    {
        $email = \sprintf('%s+%s@ejemplo.com', $local, $this->run);

        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => 'Valida1!',
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumePendingEvents();

        return $email;
    }

    private function resend(string $email): void
    {
        $this->client->request('POST', '/api/v1/auth/activation/resend', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => \sprintf('198.51.%d.%d', random_int(0, 255), random_int(1, 254)),
        ], content: json_encode(['email' => $email], \JSON_THROW_ON_ERROR));
    }

    private function activate(string $token): void
    {
        $this->client->request('POST', '/api/v1/auth/activate', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $token],
            \JSON_THROW_ON_ERROR,
        ));
    }

    private function tokenOfTheLastEmail(): string
    {
        $emails = self::getMailerMessages();
        self::assertNotEmpty($emails, 'No ha salido ningún correo.');

        $last = $emails[\count($emails) - 1];
        self::assertInstanceOf(MimeEmail::class, $last);

        self::assertSame(1, preg_match('/token=([0-9a-f]{64})/', (string) $last->getTextBody(), $matches));

        return $matches[1];
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

    private function consumePendingEvents(): void
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.integration');

        /** @var SerializerInterface $serializer */
        $serializer = self::getContainer()->get(IntegrationEventSerializer::class);

        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);

        foreach ($transport->getSent() as $envelope) {
            /** @var array{body: string, headers: array<string, string>} $wire */
            $wire = $serializer->encode($envelope);
            $bus->dispatch($serializer->decode($wire)->with(new ReceivedStamp('integration')));
        }
    }
}
