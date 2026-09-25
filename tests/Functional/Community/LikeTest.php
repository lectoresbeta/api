<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los «me gusta» sobre una publicación (`FEAT-COM-008`) y sobre un comentario
 * (`FEAT-COM-030`).
 *
 * **Ojo con el nombre**: en `Feedback` una «valoración positiva» de un
 * comentario de corrección otorga créditos (`FEAT-FBK-006`). Esto es otra
 * cosa —un gesto social, sin efecto económico— y por eso vive en otro
 * contexto. Comparten palabra y no concepto.
 *
 * Lo que más importa de estas pruebas es lo que no se ve: **no se puede
 * apoyar lo que no se puede ver**. Es el agujero clásico de los contadores —
 * no hace falta leer una publicación para saber, por el error que devuelve,
 * que está ahí.
 */
final class LikeTest extends EconomyScenario
{
    /**
     * `RN-1` a `RN-3`: alternable, uno por persona, e idempotente en las dos
     * direcciones.
     *
     * La idempotencia no es elegancia: el botón se pulsa dos veces sin querer
     * y el cliente reintenta cuando la red falla.
     */
    public function testALikeTogglesAndRepeatingItChangesNothing(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $postId = $this->publish($autora['token'], 'Algo que contar');

        self::assertSame(1, $this->like($lectora['token'], $postId));
        self::assertSame(1, $this->like($lectora['token'], $postId), 'Repetirlo no suma.');

        self::assertSame(0, $this->unlike($lectora['token'], $postId));
        self::assertSame(0, $this->unlike($lectora['token'], $postId), 'Y quitarlo dos veces no baja de cero.');
    }

    /**
     * `RN-7`: la tarjeta dice si quien mira lo ha apoyado, para que el botón
     * salga resaltado sin una petición por corazón.
     */
    public function testTheCardSaysWhetherTheViewerLikedIt(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $postId = $this->publish($autora['token'], 'Algo que contar');

        self::assertFalse($this->cardOf($lectora['token'], $postId)['likedByViewer']);

        $this->like($lectora['token'], $postId);

        self::assertTrue($this->cardOf($lectora['token'], $postId)['likedByViewer']);
        self::assertFalse(
            $this->cardOf($autora['token'], $postId)['likedByViewer'],
            'Y es de quien mira, no de la publicación.',
        );
        self::assertSame(1, $this->cardOf($autora['token'], $postId)['likeCount']);
    }

    /**
     * `RN-4`, la regla que impide el agujero: **no se apoya lo que no se
     * ve**, y el error es el mismo que si no existiera.
     */
    public function testYouCannotLikeWhatYouCannotSee(): void
    {
        $autora = $this->activatedPerson('autora');
        $extranya = $this->activatedPerson('extranya');
        $postId = $this->publish($autora['token'], 'Solo para quien me sigue', 'FOLLOWERS');

        $this->client->request('PUT', \sprintf('/api/v1/posts/%s/like', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$extranya['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Igual que si no existiera.');
    }

    /**
     * `RN-5`: **se puede apoyar lo propio**.
     *
     * Suena raro y es lo correcto: nadie lo prohíbe en ninguna red, y
     * prohibirlo obligaría a explicar por qué.
     */
    public function testYouCanLikeYourOwnPost(): void
    {
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Me ha quedado bien');

        self::assertSame(1, $this->like($autora['token'], $postId));
    }

    /**
     * `RN-9`: borrar la publicación se lleva sus apoyos. Filas apuntando a
     * algo que ya no está solo sirven para que un día alguien las cuente.
     */
    public function testDeletingThePostTakesItsLikesWithIt(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $postId = $this->publish($autora['token'], 'Efímera');

        $this->like($lectora['token'], $postId);

        $this->client->request('DELETE', \sprintf('/api/v1/posts/%s', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();

        $this->client->request('PUT', \sprintf('/api/v1/posts/%s/like', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `FEAT-COM-030`: el mismo mecanismo sobre un comentario, con su contador
     * **independiente** del de la publicación.
     */
    public function testACommentHasItsOwnLikeCounter(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $postId = $this->publish($autora['token'], 'Abrimos hilo');
        $commentId = $this->comment($postId, $lectora['token'], 'Pues yo creo que…');

        self::assertSame(1, $this->likeComment($autora['token'], $commentId));

        self::assertSame(0, $this->cardOf($autora['token'], $postId)['likeCount'], 'El de la publicación no se mueve.');

        $comentario = $this->commentRow($postId, $autora['token'], $commentId);
        self::assertSame(1, $comentario['likeCount']);
        self::assertTrue($comentario['likedByViewer']);
    }

    /**
     * `RN-5` de `FEAT-COM-030`: una respuesta se apoya igual que un
     * comentario. Son la misma entidad con un padre, así que no hay nada que
     * distinguir.
     */
    public function testAReplyIsLikedLikeAnyOtherComment(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $postId = $this->publish($autora['token'], 'Abrimos hilo');
        $commentId = $this->comment($postId, $lectora['token'], 'Pues yo creo que…');

        $this->client->request('POST', \sprintf('/api/v1/comments/%s/replies', $commentId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['body' => 'Y yo que no'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $replyId = (string) $this->payload()['commentId'];
        $this->capture();

        self::assertSame(1, $this->likeComment($lectora['token'], $replyId));
    }

    /**
     * Y tampoco se apoya un comentario de una publicación que no se ve: un
     * comentario no tiene audiencia propia, hereda entera la de su
     * publicación.
     */
    public function testYouCannotLikeACommentOnAPostYouCannotSee(): void
    {
        $autora = $this->activatedPerson('autora');
        $extranya = $this->activatedPerson('extranya');
        $postId = $this->publish($autora['token'], 'Solo para quien me sigue', 'FOLLOWERS');
        $commentId = $this->comment($postId, $autora['token'], 'Me respondo yo');

        $this->client->request('PUT', \sprintf('/api/v1/comments/%s/like', $commentId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$extranya['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testWithoutASessionThereIsNothingToLike(): void
    {
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Algo que contar');

        foreach (['PUT', 'DELETE'] as $method) {
            $this->client->request($method, \sprintf('/api/v1/posts/%s/like', $postId));
            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED, $method);
        }
    }

    private function like(string $token, string $postId): int
    {
        return $this->toggle('PUT', \sprintf('/api/v1/posts/%s/like', $postId), $token);
    }

    private function unlike(string $token, string $postId): int
    {
        return $this->toggle('DELETE', \sprintf('/api/v1/posts/%s/like', $postId), $token);
    }

    private function likeComment(string $token, string $commentId): int
    {
        return $this->toggle('PUT', \sprintf('/api/v1/comments/%s/like', $commentId), $token);
    }

    private function toggle(string $method, string $path, string $token): int
    {
        $this->client->request($method, $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseIsSuccessful();
        $this->capture();

        return (int) $this->payload()['likeCount'];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardOf(string $token, string $postId): array
    {
        $this->client->request('GET', '/api/v1/posts?scope=MINE_AND_FOLLOWED', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        foreach ($this->payload()['posts'] as $card) {
            if ($postId === $card['postId']) {
                return $card;
            }
        }

        self::fail('Esa publicación no está en el muro de quien mira.');
    }

    /**
     * @return array<string, mixed>
     */
    private function commentRow(string $postId, string $token, string $commentId): array
    {
        $this->client->request('GET', \sprintf('/api/v1/posts/%s/comments', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        foreach ($this->payload()['comments'] as $comment) {
            if ($commentId === $comment['commentId']) {
                return $comment;
            }
        }

        self::fail('Ese comentario no está en la lista.');
    }

    private function publish(string $token, string $body, string $audience = 'EVERYONE'): string
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body, 'audience' => $audience], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    private function comment(string $postId, string $token, string $body): string
    {
        $this->client->request('POST', \sprintf('/api/v1/posts/%s/comments', $postId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['commentId'];
    }
}
