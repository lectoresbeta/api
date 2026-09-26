<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compartir fuera de la plataforma (`FEAT-WRK-011`, `FEAT-COM-020`).
 *
 * **No se genera ningún enlace.** El enlace es la dirección canónica de
 * siempre; lo que el backend añade son los metadatos con los que WhatsApp o
 * Twitter pintan la previsualización. Nada que caduque, nada que revocar,
 * nada que se filtre.
 *
 * La regla que lo sostiene, y la mitad de las pruebas de este fichero:
 * **compartir no abre nada**. Solo hay tarjeta de lo que ya era visible para
 * cualquiera, porque una tarjeta la pide un rastreador sin sesión y acaba en
 * la caché de una red social.
 */
final class ShareCardTest extends EconomyScenario
{
    /**
     * `FEAT-WRK-011` `RN-1`: la tarjeta de una obra publicada trae su enlace
     * canónico y sus metadatos.
     */
    public function testAPublishedWorkHasAShareCard(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora, 'La ciudad de los pájaros');

        $this->client->request('GET', \sprintf('/api/v1/works/%s/share', $workId));
        self::assertResponseIsSuccessful();

        $card = $this->payload();

        self::assertSame('La ciudad de los pájaros', $card['title']);
        self::assertStringContainsString($workId, $card['url']);
        self::assertArrayHasKey('description', $card);
    }

    /**
     * **Sin sesión**, que es lo que la hace útil: quien la pide es un
     * rastreador.
     */
    public function testItIsPublic(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora);

        $this->client->request('GET', \sprintf('/api/v1/works/%s/share', $workId));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    /**
     * `RN-2`: **un borrador no tiene tarjeta**, y responde lo mismo que una
     * obra que no existe.
     *
     * Decir cuál de las dos es contaría que hay una obra inédita ahí, que es
     * más de lo que un extraño tiene que saber.
     */
    public function testADraftHasNoCardAndSaysNothingAboutIt(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'Todavía en borrador');
        $this->addChapter($workId, $autora['token'], words: 900);
        $this->consumeEverything();

        $this->client->request('GET', \sprintf('/api/v1/works/%s/share', $workId));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $borrador = $this->payload()['code'];

        $this->client->request('GET', '/api/v1/works/0192f000-0000-7000-8000-000000000000/share');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertSame($borrador, $this->payload()['code'], 'La diferencia está donde no se ve.');
    }

    /**
     * `FEAT-COM-020` `RN-1`: una publicación pública trae su tarjeta.
     */
    public function testAPublicPostHasAShareCard(): void
    {
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Hoy he escrito mil palabras', 'EVERYONE');

        $this->client->request('GET', \sprintf('/api/v1/posts/%s/share', $postId));
        self::assertResponseIsSuccessful();

        $card = $this->payload();

        self::assertStringContainsString($postId, $card['url']);
        self::assertSame('Hoy he escrito mil palabras', $card['description']);
    }

    /**
     * `FEAT-COM-020` `RN-2`: **una publicación para seguidores no se
     * comparte.**.
     *
     * Es la prueba que importa del fichero. Compartir no puede ser la puerta
     * de atrás de la audiencia que su autor eligió, y una tarjeta es pública
     * por definición.
     */
    public function testAFollowersOnlyPostHasNoCard(): void
    {
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Solo para quien me sigue', 'FOLLOWERS');

        $this->client->request('GET', \sprintf('/api/v1/posts/%s/share', $postId));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Y una eliminada tampoco, con la misma respuesta.
     */
    public function testADeletedPostHasNoCard(): void
    {
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Esto lo borro', 'EVERYONE');

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', \sprintf('/api/v1/posts/%s/share', $postId));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `FEAT-COM-020` `RN-3`: **si la publicación cita una obra, manda la
     * obra.**.
     *
     * Quien comparte «mirad esto» con un relato dentro quiere que se vea el
     * relato, no las dos primeras líneas de su comentario.
     */
    public function testWhenThePostCitesAWorkTheWorkWins(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora, 'La obra citada');

        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode([
            'body' => 'Mirad esto',
            'workId' => $workId,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $postId = (string) $this->payload()['postId'];
        $this->capture();

        $this->client->request('GET', \sprintf('/api/v1/posts/%s/share', $postId));
        self::assertResponseIsSuccessful();

        self::assertSame('La obra citada', $this->payload()['title']);
    }

    /**
     * Y si la obra citada deja de ser visible, la tarjeta **vuelve a hablar
     * de la publicación** en vez de romperse.
     *
     * Es el mismo comportamiento que la tarjeta viva de `FEAT-COM-028`: el
     * texto es de quien lo escribió y se queda.
     */
    public function testIfTheCitedWorkDisappearsTheCardFallsBackToThePost(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora, 'La obra retirada');

        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['body' => 'Mirad esto', 'workId' => $workId], \JSON_THROW_ON_ERROR));
        $postId = (string) $this->payload()['postId'];
        $this->capture();

        $this->client->request('DELETE', \sprintf('/api/v1/works/%s', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['confirm' => true], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->consumeEverything();

        $this->client->request('GET', \sprintf('/api/v1/posts/%s/share', $postId));
        self::assertResponseIsSuccessful();

        self::assertSame('Mirad esto', $this->payload()['description']);
    }

    /**
     * **La tarjeta no lleva a nadie dentro**, y no es un olvido: la pide un
     * rastreador anónimo, y poner ahí el nombre de quien escribe obligaría a
     * resolver su privacidad de perfil para alguien sin sesión.
     */
    public function testTheCardNamesTheContentAndNotThePerson(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora);

        $this->client->request('GET', \sprintf('/api/v1/works/%s/share', $workId));

        $card = $this->payload();

        self::assertArrayNotHasKey('author', $card);
        self::assertArrayNotHasKey('authorId', $card);
        self::assertSame(['url', 'title', 'description', 'imageUrl'], array_keys($card));
    }

    /**
     * @param array{token: string, userId: string} $author
     */
    private function aPublishedWork(array $author, string $title = 'La ciudad de los pájaros'): string
    {
        $workId = $this->createWork($author['token'], $title);
        $this->addChapter($workId, $author['token'], words: 900);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        return $workId;
    }

    private function publish(string $token, string $body, string $audience): string
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body, 'audience' => $audience], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }
}
