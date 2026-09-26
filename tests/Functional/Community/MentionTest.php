<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menciones a usuarios (`FEAT-COM-032`).
 *
 * Parece un detalle de interfaz y no lo es: **cómo se guarde una mención
 * determina si seguirá siendo correcta dentro de seis meses**.
 *
 * Guardar «Juanjo Estévez» la dejaría mostrando un nombre antiguo para
 * siempre. Guardar «@juanjoestevez» es peor: los nombres de usuario se
 * reciclan a los 30 días, así que la mención acabaría atribuyendo palabras a
 * quien no las dijo. Se guarda el `UserId`, y las dos pruebas que lo
 * demuestran son las que siguen al nombre cambiado.
 */
final class MentionTest extends EconomyScenario
{
    public function testAMentionIsServedApartFromTheTextWithTheCurrentName(): void
    {
        $autora = $this->person('autora', 'Ana García');
        $juanjo = $this->person('juanjo', 'Juanjo Estévez');

        $postId = $this->publishMentioning($autora['token'], 'Juanjo Estévez qué bien, gracias', [
            ['userId' => $juanjo['userId'], 'position' => 0],
        ]);

        $this->wall($autora['token']);
        $post = $this->postOf($postId);

        self::assertCount(1, $post['mentions']);
        self::assertSame($juanjo['userId'], $post['mentions'][0]['userId']);
        self::assertSame('Juanjo Estévez', $post['mentions'][0]['name']);
        self::assertSame(0, $post['mentions'][0]['position']);
    }

    /**
     * **La prueba que demuestra que es una referencia.** Cambia el nombre y
     * la mención de ayer muestra el de hoy, sin tocar nada.
     */
    public function testChangingTheNameUpdatesEveryPastMention(): void
    {
        $autora = $this->person('autora');
        $juanjo = $this->person('juanjo', 'Juanjo Estévez');

        $postId = $this->publishMentioning($autora['token'], 'Gracias Juanjo Estévez', [
            ['userId' => $juanjo['userId'], 'position' => 8],
        ]);

        $this->client->request('PATCH', '/api/v1/me/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$juanjo['token'],
        ], content: json_encode(['name' => 'Juan José Estévez'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();

        $this->wall($autora['token']);
        self::assertSame('Juan José Estévez', $this->postOf($postId)['mentions'][0]['name']);
    }

    /**
     * **Y la que demuestra que no es el `@usuario`.** Cambiarlo no mueve la
     * mención de destino, que es justo lo que pasaría si se hubiera guardado
     * el nombre de usuario: a los 30 días ese nombre se recicla y podría
     * señalar a otra persona.
     */
    public function testChangingTheUsernameDoesNotMoveAnyMention(): void
    {
        $autora = $this->person('autora');
        $juanjo = $this->person('juanjo', 'Juanjo Estévez');

        $postId = $this->publishMentioning($autora['token'], 'Gracias', [
            ['userId' => $juanjo['userId'], 'position' => 0],
        ]);

        $this->client->request('PUT', '/api/v1/me/username', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$juanjo['token'],
        ], content: json_encode(['username' => 'otronombre'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();

        $this->wall($autora['token']);
        self::assertSame($juanjo['userId'], $this->postOf($postId)['mentions'][0]['userId']);
    }

    /**
     * `RN-7`: no se pueden falsificar. El identificador lo comprueba el
     * servidor; si se resolviera leyendo el texto, cualquiera fabricaría una
     * mención que pareciera apuntar a otra persona.
     */
    public function testAMentionToSomebodyWhoDoesNotExistIsRefused(): void
    {
        $autora = $this->person('autora');

        $this->post('/api/v1/posts', $autora['token'], [
            'body' => 'Gracias Fulano',
            'mentions' => [['userId' => '01999999-9999-7999-8999-999999999999', 'position' => 8]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('MENTIONED_USER_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * Escribir el nombre de alguien **no es mencionarle**. Sin el
     * identificador no hay mención, y esa es la diferencia entre una
     * referencia y una cadena que se parece a una.
     */
    public function testWritingSomebodysNameByHandDoesNotMentionThem(): void
    {
        $autora = $this->person('autora');
        $juanjo = $this->person('juanjo', 'Juanjo Estévez');

        $postId = $this->publishMentioning($autora['token'], 'Gracias @juanjo Juanjo Estévez', []);

        $this->wall($autora['token']);
        self::assertSame([], $this->postOf($postId)['mentions']);
    }

    public function testACommentCarriesItsMentionsToo(): void
    {
        $autora = $this->person('autora');
        $juanjo = $this->person('juanjo', 'Juanjo Estévez');

        $postId = $this->publishMentioning($autora['token'], 'Algo', []);

        $this->post(\sprintf('/api/v1/posts/%s/comments', $postId), $juanjo['token'], [
            'body' => 'Ana García, enhorabuena',
            'mentions' => [['userId' => $autora['userId'], 'position' => 0]],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        $this->client->request('GET', \sprintf('/api/v1/posts/%s/comments', $postId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        self::assertSame($autora['userId'], $this->payload()['comments'][0]['mentions'][0]['userId']);
    }

    /**
     * `RN-3`: mencionar **no concede acceso a nada**. Si la audiencia le
     * excluye, seguirá sin verlo aunque le hayan nombrado.
     */
    public function testBeingMentionedGivesAccessToNothing(): void
    {
        $autora = $this->person('autora');
        $extrana = $this->person('extrana', 'Extraña Persona');

        $postId = $this->publishMentioning($autora['token'], 'Extraña Persona, mira esto', [
            ['userId' => $extrana['userId'], 'position' => 0],
        ], 'FOLLOWERS');

        $this->wall($extrana['token']);
        self::assertSame([], $this->payload()['posts'], 'La mención no abre la puerta.');

        self::assertSame($postId, $this->firstOf($autora['token']), 'Y su autora sí la ve.');
    }

    /**
     * `RN-4`, la pareja de la anterior y la que cierra la fuga: **no se avisa
     * a quien no puede ver dónde se le menciona**. El aviso contaría que
     * existe una publicación escrita para otros.
     *
     * La comprobación se hace aquí, en el contexto que conoce la audiencia, y
     * no se delega en quien envía el aviso.
     */
    public function testNobodyIsToldAboutAMentionTheyCannotSee(): void
    {
        $autora = $this->person('autora');
        $extrana = $this->person('extrana');
        $seguidora = $this->person('seguidora');

        $this->follow($seguidora['token'], $autora['userId']);

        $this->post('/api/v1/posts', $autora['token'], [
            'body' => 'Los dos',
            'audience' => 'FOLLOWERS',
            'mentions' => [
                ['userId' => $extrana['userId'], 'position' => 0],
                ['userId' => $seguidora['userId'], 'position' => 4],
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $avisados = array_map(
            static fn (array $event): string => (string) $event['mentionedUserId'],
            $this->announced('UserMentioned'),
        );

        self::assertSame([$seguidora['userId']], $avisados);
    }

    public function testNobodyIsToldAboutMentioningThemselves(): void
    {
        $autora = $this->person('autora');

        $this->post('/api/v1/posts', $autora['token'], [
            'body' => 'Yo misma',
            'mentions' => [['userId' => $autora['userId'], 'position' => 0]],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertSame([], $this->announced('UserMentioned'));
    }

    /**
     * `I-11`: un tope. Sin él, una publicación con doscientas menciones es un
     * envío masivo de avisos que cualquiera puede disparar.
     */
    public function testThereIsALimitToHowManyPeopleOnePostCanName(): void
    {
        $autora = $this->person('autora');
        $juanjo = $this->person('juanjo');

        $mentions = array_fill(0, 11, ['userId' => $juanjo['userId'], 'position' => 0]);

        $this->post('/api/v1/posts', $autora['token'], ['body' => 'Todos', 'mentions' => $mentions]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('TOO_MANY_MENTIONS', $this->payload()['code']);
    }

    /**
     * Nombrar dos veces a la misma persona en un texto es normal al escribir.
     * Avisarle dos veces, no.
     */
    public function testNamingSomebodyTwiceAnnouncesItOnce(): void
    {
        $autora = $this->person('autora');
        $juanjo = $this->person('juanjo');

        $this->post('/api/v1/posts', $autora['token'], [
            'body' => 'Juanjo, y otra vez Juanjo',
            'mentions' => [
                ['userId' => $juanjo['userId'], 'position' => 0],
                ['userId' => $juanjo['userId'], 'position' => 18],
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertCount(1, $this->announced('UserMentioned'));
    }

    /**
     * @param list<array<string, mixed>> $mentions
     */
    private function publishMentioning(string $token, string $body, array $mentions, string $audience = 'EVERYONE'): string
    {
        $this->post('/api/v1/posts', $token, [
            'body' => $body,
            'audience' => $audience,
            'mentions' => $mentions,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    /**
     * @return array<string, mixed>
     */
    private function postOf(string $postId): array
    {
        foreach ($this->payload()['posts'] as $post) {
            if ($post['postId'] === $postId) {
                return $post;
            }
        }

        self::fail('La publicación no está en el muro.');
    }

    private function firstOf(string $token): string
    {
        $this->wall($token);

        return (string) $this->payload()['posts'][0]['postId'];
    }

    private function wall(string $token): void
    {
        $this->client->request('GET', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, string $token, array $body): void
    {
        $this->client->request('POST', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{token: string, userId: string}
     */
    private function person(string $local, string $name = 'Alguien Con Nombre'): array
    {
        $person = $this->activatedPerson($local);

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => $name, 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        return $person;
    }

    private function follow(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();
    }
}
