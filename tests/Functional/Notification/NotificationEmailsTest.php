<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Notification;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Port\Mailer;

/**
 * Los avisos por correo (`FEAT-NOT-002`) y las preferencias que los apagan
 * (`FEAT-NOT-003`).
 *
 * Hasta ahora **no salía ni un correo de notificación**: salían los
 * operativos, cada uno con su consumidor escrito a mano, y todo lo demás se
 * quedaba en la campana esperando a que alguien entrara a mirar. Eso vaciaba
 * de sentido media pantalla de Configuración, que lleva desde `FEAT-USR-039`
 * ofreciendo un interruptor de correo que no aplicaba nadie.
 *
 * Lo que estas pruebas defienden, por orden de importancia: que **un fallo
 * del proveedor no pierda el correo**, que **los dos canales se apaguen por
 * separado**, y que **el correo no lleve contenido**.
 */
final class NotificationEmailsTest extends NotificationScenario
{
    private FlakyMailer $mail;

    /**
     * El buzón se sustituye **antes de la primera petición**, y no cuando
     * hace falta: el contenedor de pruebas no deja reemplazar un servicio ya
     * construido, y la primera petición construye el que manda el correo de
     * activación.
     *
     * Por eso también es **uno solo y mutable** en vez de uno nuevo por
     * caso: hay una prueba que necesita que el proveedor se caiga y se
     * levante a mitad.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mail = new FlakyMailer();
        self::getContainer()->set(Mailer::class, $this->mail);
    }

    /**
     * Un aviso cuyo tipo existe por correo genera un correo, y uno que solo
     * existe en la plataforma no.
     *
     * El segundo importa tanto como el primero: los tipos de ritmo social
     * —una respuesta, un me gusta, un mensaje— pasan muchas veces al día, y
     * un correo por cada uno enseña a ignorar el remitente, que es la forma
     * más rápida de que el correo que sí importa tampoco se lea.
     */
    public function testWhatTheCatalogueSaysGoesByEmailGoesByEmail(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($autora, 'La obra avisada');

        $this->clearMail();
        $this->ask($workId, $lectora['token']);
        $this->consumeEverything();

        $asuntos = $this->subjects();

        self::assertContains(
            'Alguien quiere ser lector beta de una obra tuya',
            $asuntos,
            'ACCESS_REQUESTED existe por correo.',
        );
    }

    /**
     * `RN-4`: **el correo no lleva contenido.** Ni una línea de una obra, de
     * una corrección o de un mensaje.
     *
     * Lleva qué ha pasado, quién y dónde mirarlo. Quien lo recibe tiene que
     * entrar a verlo, no leerlo en el buzón: lo inédito no sale de la
     * plataforma por correo.
     */
    public function testTheEmailCarriesNoContent(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($autora, 'La obra callada');

        $this->clearMail();
        $this->ask($workId, $lectora['token']);
        $this->consumeEverything();

        foreach ($this->bodies() as $cuerpo) {
            self::assertStringNotContainsString(
                'palabra palabra',
                $cuerpo,
                'El texto del capítulo no sale de la plataforma.',
            );
        }
    }

    /**
     * `RN-2`: el mismo hecho reentregado **no manda dos correos**.
     *
     * `consumeEverything()` reentrega cada hecho a propósito, así que esta
     * prueba se ejecuta contra el peor caso de RabbitMQ sin tener que
     * simularlo.
     */
    public function testARedeliveredFactDoesNotSendASecondEmail(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($autora, 'La obra repetida');

        $this->clearMail();
        $this->ask($workId, $lectora['token']);
        $this->consumeEverything();
        $this->consumeEverything();

        $solicitudes = array_filter(
            $this->subjects(),
            static fn (string $asunto): bool => 'Alguien quiere ser lector beta de una obra tuya' === $asunto,
        );

        self::assertCount(1, $solicitudes);
    }

    /**
     * `RN-3`, y es la regla por la que existe la columna `emailed_at`:
     * **si el proveedor falla, el reintento vuelve a intentarlo**.
     *
     * Sin distinguir «ya se mandó» de «existe el aviso», el reintento
     * encontraría la fila, se daría por hecho, y el primer fallo del
     * proveedor dejaría a alguien sin su correo para siempre. La
     * deduplicación taparía justo el caso que hay que reparar.
     */
    public function testAProviderFailureIsRetriedAndTheEmailStillArrives(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($autora, 'La obra reintentada');

        $this->clearMail();
        $this->mail->down = true;
        $this->ask($workId, $lectora['token']);

        try {
            $this->consumeEverything();
        } catch (\RuntimeException) {
            // Lo que haría RabbitMQ: la excepción sube y el mensaje vuelve.
        }

        self::assertGreaterThan(0, $this->mail->attempts, 'Se intentó.');
        self::assertSame([], $this->subjects(), 'Y no salió.');

        $this->mail->down = false;
        $this->consumeEverything();

        self::assertContains(
            'Alguien quiere ser lector beta de una obra tuya',
            $this->subjects(),
            'El reintento lo manda: la fila existía pero el correo no había salido.',
        );
    }

    /**
     * `FEAT-NOT-003` `RN-1`: **apagar el correo de un tipo no toca la
     * campana**.
     *
     * Son dos casillas y la pantalla promete que son independientes.
     */
    public function testSilencingTheEmailLeavesTheBellAlone(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->silence($autora['token'], 'ACCESS_REQUESTED', 'EMAIL');

        $workId = $this->onRequestWork($autora, 'La obra silenciada');
        $this->clearMail();
        $this->ask($workId, $lectora['token']);
        $this->consumeEverything();

        self::assertSame([], $this->subjects(), 'Sin correo.');
        self::assertGreaterThan(0, $this->unreadOf($autora['token']), 'Y con campana.');
    }

    /**
     * Y al revés: **apagar la campana no toca el correo**.
     *
     * Es el caso que obligó a crear la fila aunque esté silenciada: sin ella
     * no habría dónde anotar que el correo salió, y el reintento mandaría
     * otro.
     */
    public function testSilencingTheBellLeavesTheEmailAlone(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->silence($autora['token'], 'ACCESS_REQUESTED', 'PLATFORM');

        $workId = $this->onRequestWork($autora, 'La obra sin campana');
        $this->clearMail();
        $this->ask($workId, $lectora['token']);
        $this->consumeEverything();

        self::assertContains('Alguien quiere ser lector beta de una obra tuya', $this->subjects());
        self::assertSame(0, $this->unreadOf($autora['token']), 'Y no se cuenta lo que no se enseña.');
    }

    /**
     * `RN-3` de `FEAT-NOT-003`: el interruptor general **no alcanza a los
     * operativos**.
     *
     * Un catálogo que los incluyera invitaría a apagarlos, y el primero que
     * faltase dejaría a alguien sin poder recuperar su cuenta.
     */
    public function testTheMasterSwitchDoesNotReachOperationalEmails(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->client->request('PUT', '/api/v1/me/notification-preferences', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$persona['token'],
        ], content: json_encode(['allMuted' => true], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        $this->clearMail();

        $this->client->request('PUT', '/api/v1/me/password', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$persona['token'],
        ], content: json_encode([
            'currentPassword' => self::PASSWORD,
            'newPassword' => 'OtraValida1!',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertNotSame([], $this->subjects(), 'Cambiar la contraseña se avisa siempre.');
    }

    private function silence(string $token, string $topic, string $channel): void
    {
        $this->client->request('PUT', '/api/v1/me/notification-preferences', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'preferences' => [['topic' => $topic, 'channel' => $channel, 'enabled' => false]],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function unreadOf(string $token): int
    {
        $this->client->request('GET', '/api/v1/me/notifications/unread-count', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return (int) $this->payload()['unreadCount'];
    }

    /**
     * @return list<string>
     */
    private function subjects(): array
    {
        return array_map(
            static fn (EmailMessage $message): string => $message->subject,
            $this->mail->sent,
        );
    }

    /**
     * @return list<string>
     */
    private function bodies(): array
    {
        return array_map(
            static fn (EmailMessage $message): string => $message->text.$message->html,
            $this->mail->sent,
        );
    }

    private function clearMail(): void
    {
        $this->mail->sent = [];
    }
}

/**
 * Un buzón de mentira que se queda con lo que le mandan, y que se puede
 * tirar al suelo a mitad de una prueba.
 *
 * Falla como falla un proveedor de verdad: con una excepción que sube hasta
 * la cola, que es lo que hace que RabbitMQ reentregue.
 */
final class FlakyMailer implements Mailer
{
    /** @var list<EmailMessage> */
    public array $sent = [];

    public bool $down = false;

    public int $attempts = 0;

    public function send(EmailMessage $message): void
    {
        ++$this->attempts;

        if ($this->down) {
            throw new \RuntimeException('The mail provider is down.');
        }

        $this->sent[] = $message;
    }
}
