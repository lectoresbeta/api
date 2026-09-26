<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La sección «Mensajes» (`FEAT-COM-012`).
 *
 * Dos cosas concentran casi todas estas pruebas, y no son la lista:
 *
 * - **una conversación ajena responde `404`, no `403`**. Un `403`
 *   confirmaría que existe, y con ella que esas dos personas hablan. Quién
 *   habla con quién no se le debe a nadie;
 * - **marcar leído afecta a lo recibido, nunca a lo enviado**. Si contara lo
 *   propio, el contador de quien escribe bajaría al abrir su propio hilo.
 */
final class ConversationsTest extends EconomyScenario
{
    /**
     * `RN-2`, `RN-3`: la lista va por el último mensaje, y cada fila lleva
     * con quién es, el extracto y cuántos quedan sin leer.
     */
    public function testTheListIsOrderedByTheLastMessageAndCarriesWhatAScreenNeeds(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');
        $carla = $this->activatedPerson('carla');

        $this->send($bruno['token'], $ana['userId'], 'Primero escribo yo');
        $this->send($carla['token'], $ana['userId'], 'Y después yo');

        $rows = $this->conversationsOf($ana['token']);

        self::assertCount(2, $rows);
        self::assertSame($carla['userId'], $rows[0]['other']['userId'], 'La más reciente arriba.');
        self::assertSame('Y después yo', $rows[0]['lastMessage']);
        self::assertFalse($rows[0]['lastMessageIsMine']);
        self::assertSame(1, $rows[0]['unreadCount']);
        self::assertSame($bruno['userId'], $rows[1]['other']['userId']);

        // Y contestar sube esa conversación arriba.
        $this->send($ana['token'], $bruno['userId'], 'Perdona, no te vi');

        $rows = $this->conversationsOf($ana['token']);
        self::assertSame($bruno['userId'], $rows[0]['other']['userId']);
        self::assertTrue($rows[0]['lastMessageIsMine'], 'Para pintar el «Tú:».');
        self::assertSame(
            1,
            $rows[0]['unreadCount'],
            'Contestar no marca leído: lo hace la pantalla al abrir, y sigue habiendo un recibido sin leer.',
        );
    }

    /**
     * El extracto es un extracto: la lista no reparte conversaciones enteras.
     */
    public function testTheListCarriesAnExcerptAndNotTheWholeMessage(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->send($bruno['token'], $ana['userId'], str_repeat('a', 400));

        $rows = $this->conversationsOf($ana['token']);
        self::assertSame(140, mb_strlen((string) $rows[0]['lastMessage']));
    }

    /**
     * `RN-1`: una conversación ajena no existe. El `404` es el mismo que el
     * de un identificador inventado, y esa es toda la gracia.
     */
    public function testSomebodyElsesConversationIsIndistinguishableFromNone(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');
        $curiosa = $this->activatedPerson('curiosa');

        $conversationId = $this->send($ana['token'], $bruno['userId'], 'Entre nosotros');

        $this->client->request('GET', \sprintf('/api/v1/conversations/%s/messages', $conversationId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$curiosa['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $ajena = $this->payload();

        $this->client->request('GET', '/api/v1/conversations/11111111-1111-4111-8111-111111111111/messages', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$curiosa['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertSame($ajena, $this->payload(), 'Exactamente la misma respuesta.');
        self::assertSame('CONVERSATION_NOT_FOUND', $ajena['code']);
    }

    /**
     * Y tampoco se marca como leída la de otro, por lo mismo.
     */
    public function testYouCannotMarkSomebodyElsesConversationRead(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');
        $curiosa = $this->activatedPerson('curiosa');

        $conversationId = $this->send($ana['token'], $bruno['userId'], 'Entre nosotros');

        $this->client->request('PUT', \sprintf('/api/v1/conversations/%s/read', $conversationId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$curiosa['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-4`: del más reciente hacia atrás, que es como se lee una
     * conversación — al abrirla hace falta lo último, no lo primero.
     */
    public function testMessagesComeNewestFirst(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $conversationId = $this->send($ana['token'], $bruno['userId'], 'Uno');
        $this->send($bruno['token'], $ana['userId'], 'Dos');
        $this->send($ana['token'], $bruno['userId'], 'Tres');

        $rows = $this->messagesOf($ana['token'], $conversationId);

        self::assertSame(['Tres', 'Dos', 'Uno'], array_column($rows, 'body'));
        self::assertSame([true, false, true], array_column($rows, 'mine'));
    }

    /**
     * `RN-5`, `RN-6`: marcar leído toca **lo recibido**, es idempotente, y no
     * baja el contador de la otra parte.
     */
    public function testMarkingReadTouchesWhatYouReceivedAndOnlyThat(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $conversationId = $this->send($bruno['token'], $ana['userId'], 'Uno');
        $this->send($bruno['token'], $ana['userId'], 'Dos');
        $this->send($ana['token'], $bruno['userId'], 'Ya te leo');

        self::assertSame(2, $this->conversationsOf($ana['token'])[0]['unreadCount']);
        self::assertSame(1, $this->conversationsOf($bruno['token'])[0]['unreadCount']);

        self::assertSame(0, $this->markRead($ana['token'], $conversationId));
        self::assertSame(0, $this->markRead($ana['token'], $conversationId), 'Repetirlo no cambia nada.');

        self::assertSame(
            1,
            $this->conversationsOf($bruno['token'])[0]['unreadCount'],
            'Que Ana lea no lee por Bruno.',
        );
    }

    /**
     * `RN-7`: con quien hay bloqueo **no aparece**, en ninguno de los dos
     * sentidos, y **no se borra**. El bloqueo se puede deshacer, y entonces
     * el hilo vuelve entero.
     */
    public function testABlockedThreadDisappearsAndComesBackWhole(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $conversationId = $this->send($ana['token'], $bruno['userId'], 'Lo que hablamos');

        $this->block($ana['token'], $bruno['userId']);

        self::assertSame([], $this->conversationsOf($ana['token']), 'Quien bloqueó no la ve.');
        self::assertSame([], $this->conversationsOf($bruno['token']), 'Ni quien fue bloqueada.');

        $this->unblock($ana['token'], $bruno['userId']);

        $rows = $this->conversationsOf($ana['token']);
        self::assertCount(1, $rows);
        self::assertSame($conversationId, $rows[0]['conversationId']);
        self::assertSame('Lo que hablamos', $rows[0]['lastMessage'], 'Con todo dentro.');
    }

    public function testWithoutASessionThereIsNoInbox(): void
    {
        $this->client->request('GET', '/api/v1/me/conversations');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conversationsOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/conversations', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->payload()['data'];

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messagesOf(string $token, string $conversationId): array
    {
        $this->client->request('GET', \sprintf('/api/v1/conversations/%s/messages', $conversationId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->payload()['data'];

        return $rows;
    }

    private function markRead(string $token, string $conversationId): int
    {
        $this->client->request('PUT', \sprintf('/api/v1/conversations/%s/read', $conversationId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();

        return (int) $this->payload()['unreadCount'];
    }

    private function block(string $token, string $userId): void
    {
        $this->relationship('PUT', $token, $userId);
    }

    private function unblock(string $token, string $userId): void
    {
        $this->relationship('DELETE', $token, $userId);
    }

    private function relationship(string $method, string $token, string $userId): void
    {
        $this->client->request($method, \sprintf('/api/v1/users/%s/block', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function send(string $token, string $recipientId, string $body): string
    {
        $this->client->request('POST', \sprintf('/api/v1/users/%s/messages', $recipientId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['conversationId'];
    }
}
