<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Notification;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lo que comparten las pruebas de `Notification`: **provocar hechos reales**.
 *
 * Ni un solo aviso se fabrica aquí a mano. Todos nacen de llamar a la API de
 * `Reading` o de `Feedback` y dejar que el hecho viaje por el serializador
 * real, que es lo único que demuestra que los dos contextos se entienden sin
 * compartir una clase.
 */
abstract class NotificationScenario extends EconomyScenario
{
    /**
     * Una obra publicada y abierta a solicitud, que es la modalidad con la
     * que nace toda obra.
     *
     * @param array{token: string, userId: string} $author
     */
    protected function onRequestWork(array $author, string $title = 'La ciudad de los pájaros'): string
    {
        $workId = $this->createWork($author['token'], $title);
        $this->addChapter($workId, $author['token'], words: 900);
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');

        return $workId;
    }

    protected function ask(string $workId, string $token): string
    {
        $this->client->request('POST', \sprintf('/api/v1/works/%s/access-requests', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['requestId'];
    }

    protected function resolveRequest(string $requestId, string $token, string $decision): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/access-requests/%s/resolution', $requestId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => $decision], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    protected function invite(string $workId, string $token, string $userId): string
    {
        $this->client->request('POST', \sprintf('/api/v1/works/%s/beta-reader-invitations', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['userId' => $userId], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['invitationId'];
    }

    protected function resolveInvitation(string $invitationId, string $token, string $decision): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/beta-reader-invitations/%s/resolution', $invitationId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => $decision], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    protected function revoke(string $workId, string $readerId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/works/%s/beta-readers/%s', $workId, $readerId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
    }

    protected function changeStatus(string $workId, string $token, string $status): void
    {
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $token, ['status' => $status]);
    }

    protected function accessMode(string $workId, string $token, string $mode): void
    {
        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $token, ['accessMode' => $mode]);
    }

    /**
     * @param array<string, string|int|bool> $query
     */
    protected function inbox(string $token, array $query = []): void
    {
        $this->client->request('GET', '/api/v1/me/notifications', parameters: $query, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    protected function unreadCount(string $token): int
    {
        $this->client->request('GET', '/api/v1/me/notifications/unread-count', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        return (int) $this->payload()['unreadCount'];
    }

    protected function markRead(string $notificationId, string $token): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/me/notifications/%s/read', $notificationId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    protected function markAllRead(string $token): void
    {
        $this->client->request('PUT', '/api/v1/me/notifications/read', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * Los tipos que hay en la bandeja, en el orden en que llegan.
     *
     * @param array<string, string|int|bool> $query
     *
     * @return list<string>
     */
    protected function kinds(string $token, array $query = []): array
    {
        $this->inbox($token, $query);
        self::assertResponseIsSuccessful();

        /** @var list<array{kind: string}> $data */
        $data = $this->payload()['data'];

        return array_map(static fn (array $notification): string => $notification['kind'], $data);
    }

    /**
     * El aviso más reciente de ese tipo, tal y como lo lee su destinatario.
     *
     * @return array<string, mixed>
     */
    protected function notice(string $token, string $kind): array
    {
        $this->inbox($token);
        self::assertResponseIsSuccessful();

        /** @var list<array{kind: string}> $data */
        $data = $this->payload()['data'];

        foreach ($data as $notification) {
            if ($notification['kind'] === $kind) {
                return $notification;
            }
        }

        self::fail(\sprintf('No hay ningún aviso de tipo %s en la bandeja.', $kind));
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function put(string $path, string $token, array $body): void
    {
        $this->client->request('PUT', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }
}
