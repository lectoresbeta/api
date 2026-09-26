<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Notification;

use Symfony\Component\HttpFoundation\Response;

/**
 * «Alguien a quien sigues ha publicado algo» (`FEAT-NOT-004`).
 *
 * **Es lo que hace que seguir signifique algo.** Hasta ahora seguir a un
 * autor cambiaba lo que salía en el muro y nada más: quien no entrara ese día
 * se perdía la publicación y no se enteraba nunca.
 *
 * Tres de estas pruebas son sobre **no avisar**, que es la mitad difícil:
 * dejar de seguir tiene que apagarlo, bloquear también, y pulsar «Publicar»
 * dos veces no puede avisar dos veces.
 */
final class NewWorkNoticesTest extends NotificationScenario
{
    /**
     * `RN-1`, `RN-3`: llega a quien sigue, con el título dentro, y no al
     * autor.
     */
    public function testPublishingNotifiesTheFollowersAndNotTheAuthor(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $ajena = $this->activatedPerson('ajena');

        $this->follow($lectora['token'], $autora['userId']);

        $workId = $this->publishAWork($autora, 'La ciudad de los pájaros');

        $aviso = $this->notice($lectora['token'], 'SUBSCRIBED_AUTHOR_PUBLISHED');
        self::assertSame($workId, $aviso['payload']['workId']);
        self::assertSame('La ciudad de los pájaros', $aviso['payload']['workTitle']);
        self::assertSame($autora['userId'], $aviso['payload']['actorId']);

        self::assertSame([], $this->kinds($autora['token']), 'Quien publica ya sabe que ha publicado.');
        self::assertSame([], $this->kinds($ajena['token']), 'Y quien no la sigue no se entera.');
    }

    /**
     * `RN-4`: **dejar de seguir lo apaga.** Es la mitad que se olvida, y la
     * que envejece hacia el lado peligroso — un aviso que no se puede apagar
     * dejando de seguir es peor que no haberlo mandado nunca.
     */
    public function testUnfollowingStopsTheNotices(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);
        $this->unfollow($lectora['token'], $autora['userId']);

        $this->publishAWork($autora);

        self::assertSame([], $this->kinds($lectora['token']));
    }

    /**
     * Y bloquear también, **sin que este contexto sepa que hubo bloqueo**: el
     * bloqueo deshace los dos seguimientos y publica un `AuthorUnsubscribed`
     * por cada uno (`FEAT-COM-034` `RN-3`). La copia se cura sola.
     */
    public function testBlockingAlsoStopsThem(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $autora['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        $this->publishAWork($autora);

        self::assertNotContains('SUBSCRIBED_AUTHOR_PUBLISHED', $this->kinds($lectora['token']));
    }

    /**
     * `RN-2`: **pulsar «Publicar» sobre algo ya publicado no avisa de nada.**.
     *
     * La llamada responde bien —fija un estado, y ese estado queda— pero no
     * ha pasado nada, y avisar dos veces de la misma obra es la forma más
     * rápida de que la gente deje de mirar la campana.
     */
    public function testPublishingTwiceDoesNotNotifyTwice(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);
        $workId = $this->publishAWork($autora);

        $this->changeStatus($workId, $autora['token'], 'PUBLISHED');
        $this->consumeEverything();

        self::assertSame(['SUBSCRIBED_AUTHOR_PUBLISHED'], $this->kinds($lectora['token']));
        self::assertSame(1, $this->unreadCount($lectora['token']));
    }

    /**
     * Y reentregar el hecho tampoco: cada destinatario tiene su fila, y el
     * índice único es sobre `(destinatario, tipo, evento)`.
     */
    public function testRedeliveringTheFactDoesNotDuplicateTheNotice(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);
        $this->publishAWork($autora);

        $this->deliver($this->queued('WorkPublished'));
        $this->deliver($this->queued('WorkPublished'));

        self::assertSame(['SUBSCRIBED_AUTHOR_PUBLISHED'], $this->kinds($lectora['token']));
    }

    /**
     * Varios seguidores, un hecho: **cada uno su fila**, porque cada uno
     * tiene sus preferencias y su bandeja.
     */
    public function testEveryFollowerGetsTheirOwnNotice(): void
    {
        $autora = $this->activatedPerson('autora');
        $una = $this->activatedPerson('una');
        $otra = $this->activatedPerson('otra');

        $this->follow($una['token'], $autora['userId']);
        $this->follow($otra['token'], $autora['userId']);

        $this->publishAWork($autora);

        self::assertSame(['SUBSCRIBED_AUTHOR_PUBLISHED'], $this->kinds($una['token']));
        self::assertSame(['SUBSCRIBED_AUTHOR_PUBLISHED'], $this->kinds($otra['token']));
    }

    /**
     * `RN-5`: quien lo ha silenciado no lo recibe. No es operativo: es una
     * recomendación, y renunciar a ella es legítimo.
     */
    public function testSilencingItWorks(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->follow($lectora['token'], $autora['userId']);
        $this->put('/api/v1/me/notification-preferences', $lectora['token'], [
            'preferences' => [
                ['topic' => 'SUBSCRIBED_AUTHOR_PUBLISHED', 'channel' => 'PLATFORM', 'enabled' => false],
            ],
        ]);
        $this->consumeEverything();

        $this->publishAWork($autora);

        self::assertSame([], $this->kinds($lectora['token']));
    }

    /**
     * @param array{token: string, userId: string} $author
     */
    private function publishAWork(array $author, string $title = 'La ciudad de los pájaros'): string
    {
        $workId = $this->createWork($author['token'], $title);
        $this->addChapter($workId, $author['token'], words: 900);
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');
        $this->consumeEverything();

        return $workId;
    }

    private function follow(string $token, string $authorId): void
    {
        $this->relationship('PUT', $token, $authorId);
    }

    private function unfollow(string $token, string $authorId): void
    {
        $this->relationship('DELETE', $token, $authorId);
    }

    private function relationship(string $method, string $token, string $authorId): void
    {
        $this->client->request($method, \sprintf('/api/v1/users/%s/subscription', $authorId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertContains($this->client->getResponse()->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NO_CONTENT,
            Response::HTTP_CREATED,
        ]);
        $this->capture();
        $this->consumeEverything();
    }
}
