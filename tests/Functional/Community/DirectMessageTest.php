<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los mensajes directos (`FEAT-COM-011`, `FEAT-COM-012`) y la puerta que los
 * gobierna (`FEAT-USR-010`).
 *
 * Lo que de verdad se comprueba aquí es **la diferencia entre abrir y
 * continuar**. Es la regla que más fácil se lee al revés, y la que más daño
 * hace si se implementa mal en cualquiera de los dos sentidos: si el ajuste
 * también cerrara los hilos abiertos, quien endurece su privacidad
 * desaparecería de conversaciones a medias; si el bloqueo **no** los cerrara,
 * bloquear no serviría de nada contra quien ya te escribía.
 */
final class DirectMessageTest extends EconomyScenario
{
    /**
     * `RN-1`: una conversación por par. La segunda vez se reutiliza, y da
     * igual quién escriba.
     */
    public function testTheSecondMessageLandsInTheSameConversation(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $first = $this->send($ana['token'], $bruno['userId'], 'Hola, ¿lees fantasía?');
        $second = $this->send($bruno['token'], $ana['userId'], 'Poco, pero dime.');

        self::assertSame($first, $second, 'El par tiene una conversación, no dos.');
    }

    /**
     * `RN-2`: no hay conversación de una persona.
     */
    public function testYouCannotWriteToYourself(): void
    {
        $ana = $this->activatedPerson('ana');

        $this->post($ana['token'], $ana['userId'], 'Nota para mí');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CANNOT_MESSAGE_YOURSELF', $this->payload()['code']);
    }

    /**
     * `FEAT-USR-010` `RN-1`, `RN-3`: con el buzón en `FOLLOWERS` solo escribe
     * quien sigue al destinatario — y **seguir es unilateral**, así que lo
     * que cuenta es que el remitente siga a quien recibe, no al revés.
     */
    public function testFollowersOnlyLetsThroughWhoeverFollowsTheRecipient(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->messagePermission($bruno['token'], 'FOLLOWERS');

        $this->post($ana['token'], $bruno['userId'], '¿Te puedo preguntar algo?');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('MESSAGES_NOT_ACCEPTED', $this->payload()['code']);

        // Que Bruno siga a Ana no abre la puerta: el ajuste es de Bruno y
        // habla de quién le sigue a él.
        $this->follow($bruno['token'], $ana['userId']);
        $this->post($ana['token'], $bruno['userId'], '¿Ahora?');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Seguir es unilateral.');

        $this->follow($ana['token'], $bruno['userId']);
        $this->post($ana['token'], $bruno['userId'], 'Ahora sí.');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `FEAT-USR-010` `RN-2`: por defecto abierto. Quien no ha tocado nada
     * recibe mensajes de cualquiera.
     */
    public function testAnUntouchedMailboxIsOpen(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->post($ana['token'], $bruno['userId'], 'Hola');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `NOBODY` cierra el buzón del todo.
     */
    public function testNobodyClosesTheMailbox(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->messagePermission($bruno['token'], 'NOBODY');
        $this->post($ana['token'], $bruno['userId'], 'Hola');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * **La regla que da sentido a todo lo demás** (`FEAT-COM-011` `RN-4`,
     * `FEAT-USR-010` `RN-5`): endurecer el ajuste **no cierra** los hilos ya
     * abiertos.
     *
     * Si los cerrara, «prefiero que no me escriba cualquiera» significaría
     * «desaparezco de conversaciones que estaba teniendo», y quien espera una
     * respuesta a medias no volvería a recibirla.
     */
    public function testClosingTheMailboxDoesNotCloseOpenThreads(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->send($ana['token'], $bruno['userId'], 'Empezamos a hablar');
        $this->messagePermission($bruno['token'], 'NOBODY');

        $this->post($ana['token'], $bruno['userId'], '…y seguimos');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'El hilo abierto sigue abierto.');
    }

    /**
     * `RN-5`, la otra mitad: **el bloqueo sí corta lo abierto**, y en los dos
     * sentidos. Da igual quién bloqueó a quién.
     */
    public function testBlockingClosesAnOpenThreadInBothDirections(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->send($ana['token'], $bruno['userId'], 'Empezamos a hablar');
        $this->block($bruno['token'], $ana['userId']);

        $this->post($ana['token'], $bruno['userId'], '¿Hola?');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Quien fue bloqueada no escribe.');

        $this->post($bruno['token'], $ana['userId'], 'Tampoco yo');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Ni quien bloqueó.');
    }

    /**
     * `RN-7`: un destinatario que no existe o no está activado responde
     * `404`, **y no se distingue cuál de las dos**. Distinguirlo convertiría
     * el endpoint en una forma de preguntar quién está en la plataforma.
     */
    public function testAnUnknownRecipientIsIndistinguishableFromAnUnactivatedOne(): void
    {
        $ana = $this->activatedPerson('ana');
        $this->signedInWithoutActivating('sinactivar');

        $sinActivar = $this->userIdOf('sinactivar');

        $this->post($ana['token'], '11111111-1111-4111-8111-111111111111', 'Hola');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $inexistente = $this->payload();

        $this->post($ana['token'], $sinActivar, 'Hola');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertSame($inexistente['code'], $this->payload()['code'], 'La misma respuesta para los dos.');
        self::assertSame('CONVERSATION_NOT_FOUND', $inexistente['code']);
    }

    /**
     * `RN-6`: ni vacío ni por encima de 4.000 caracteres. Se recortan los
     * espacios de los extremos, así que un mensaje de solo espacios está
     * vacío.
     */
    public function testTheBodyHasToBeAMessage(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        foreach (['', '   ', str_repeat('a', 4001)] as $body) {
            $this->post($ana['token'], $bruno['userId'], $body);
            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, var_export($body, true));
            self::assertSame('VALIDATION_FAILED', $this->payload()['code']);
        }

        $this->post($ana['token'], $bruno['userId'], str_repeat('a', 4000));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'Justo en el tope sí cabe.');
    }

    /**
     * `RN-8` y `RN-9`: el destinatario recibe un aviso, y **el aviso no lleva
     * el mensaje**.
     *
     * Esta es la comprobación que no se puede quitar. `Notification` es el
     * contexto que escribe correos; si el hecho trajera el cuerpo, una
     * conversación privada acabaría en un buzón de correo el día que alguien
     * reclasificara este aviso.
     */
    public function testTheRecipientIsToldButNotWhatItSaid(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->post($ana['token'], $bruno['userId'], 'Esto es un secreto entre los dos');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $announced = $this->lastAnnouncementOf('DirectMessageSent');
        self::assertSame($bruno['userId'], $announced['recipientId']);
        self::assertSame($ana['userId'], $announced['senderId']);
        self::assertStringNotContainsString(
            'secreto',
            json_encode($announced, \JSON_THROW_ON_ERROR),
            'El hecho no lleva ni una línea del mensaje.',
        );

        $this->consumeEverything();

        $aviso = $this->firstNotificationOf($bruno['token']);
        self::assertSame('DIRECT_MESSAGE_RECEIVED', $aviso['kind']);
        self::assertStringNotContainsString(
            'secreto',
            json_encode($aviso, \JSON_THROW_ON_ERROR),
            'Ni el aviso tampoco.',
        );
    }

    /**
     * Y quien escribe no se avisa a sí mismo, ni siquiera cuando el hecho se
     * reentrega diez veces.
     */
    public function testTheSenderIsNotNotifiedAndRedeliveryAddsNothing(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->post($ana['token'], $bruno['userId'], 'Hola');
        $this->consumeEverything();

        self::assertSame(0, $this->unreadNotificationsOf($ana['token']));
        self::assertSame(1, $this->unreadNotificationsOf($bruno['token']));
    }

    private function userIdOf(string $local): string
    {
        return $this->idOfAddress($this->address($local));
    }

    private function idOfAddress(string $email): string
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $user = $users->ofEmail(Email::fromString($email));
        self::assertNotNull($user);

        return $user->id()->value();
    }

    /**
     * @return array<string, mixed>
     */
    private function firstNotificationOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/notifications', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        $rows = $this->payload()['data'];
        self::assertNotEmpty($rows, 'No ha llegado ningún aviso.');

        /** @var array<string, mixed> $first */
        $first = $rows[0];

        return $first;
    }

    private function unreadNotificationsOf(string $token): int
    {
        $this->client->request('GET', '/api/v1/me/notifications/unread-count', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return (int) $this->payload()['unreadCount'];
    }

    private function messagePermission(string $token, string $audience): void
    {
        $this->putAs('/api/v1/me/privacy-settings', $token, [
            'profileVisibility' => 'EVERYONE',
            'commentPermission' => 'EVERYONE',
            'messagePermission' => $audience,
        ]);

        $this->consumeEverything();
    }

    private function follow(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function block(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function send(string $token, string $recipientId, string $body): string
    {
        $this->post($token, $recipientId, $body);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['conversationId'];
    }

    private function post(string $token, string $recipientId, string $body): void
    {
        $this->client->request('POST', \sprintf('/api/v1/users/%s/messages', $recipientId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        $this->capture();
    }
}
