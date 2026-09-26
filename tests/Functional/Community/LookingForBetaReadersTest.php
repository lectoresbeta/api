<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publicar buscando lectores beta para una obra (`FEAT-COM-003`).
 *
 * Es la única intención de publicación que **impone algo**, y por lo que
 * significa: `GENERAL`, `LOOKING_FOR_WRITING_BUDDY` y `OFFERING_AS_BETA_READER`
 * describen lo que alguien quiere; esta pide algo concreto sobre un texto
 * concreto, y sin él nadie puede atenderla.
 */
final class LookingForBetaReadersTest extends EconomyScenario
{
    /**
     * `RN-1` a `RN-3`: con una obra propia y publicada, sale.
     */
    public function testWithYourOwnPublishedWorkItGoesOut(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora);

        $this->publish($autora['token'], $workId);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `RN-1`: **«busco lectores» sin decir para qué** es una petición que
     * nadie puede atender. Quien la lee no sabe qué se le ofrece y quien la
     * escribe no recibe a nadie.
     */
    public function testWithoutAWorkItIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->publish($autora['token'], null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('WORK_REQUIRED', $this->payload()['code']);
    }

    /**
     * `RN-2`: reclutar lectores para lo que escribió otro sería decidir por
     * él a quién enseña su texto, que es justo lo que la modalidad de acceso
     * existe para que decida su autor.
     */
    public function testYouCannotRecruitForSomebodyElsesWork(): void
    {
        $autora = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $workId = $this->aPublishedWork($autora);

        $this->publish($otra['token'], $workId);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('NOT_YOUR_WORK', $this->payload()['code']);
    }

    /**
     * `RN-3`: anunciar un borrador manda a quien responda a una puerta
     * cerrada — no puede leerlo ni pedir acceso, porque para él la obra no
     * existe.
     */
    public function testYouCannotRecruitForADraft(): void
    {
        $autora = $this->activatedPerson('autora');

        $workId = $this->createWork($autora['token'], 'Todavía en borrador');
        $this->addChapter($workId, $autora['token'], words: 900);

        $this->publish($autora['token'], $workId);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('WORK_NOT_VISIBLE', $this->payload()['code']);
    }

    /**
     * Y una obra inventada responde `404`, no una de las tres anteriores: la
     * comprobación de que existe va primero.
     */
    public function testAnUnknownWorkIsNotFound(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->publish($autora['token'], '11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * **Las otras intenciones no cambian.** Una publicación general sigue sin
     * exigir obra, y sigue pudiendo llevar un borrador propio: eso es
     * promocionar lo tuyo, no pedirle nada a nadie.
     */
    public function testTheOtherIntentionsAreUntouched(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->post($autora['token'], ['body' => 'Buenos días', 'type' => 'GENERAL']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $borrador = $this->createWork($autora['token'], 'Mi borrador');
        $this->addChapter($borrador, $autora['token'], words: 900);

        $this->post($autora['token'], [
            'body' => 'Estoy escribiendo esto',
            'type' => 'GENERAL',
            'workId' => $borrador,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->post($autora['token'], ['body' => 'Me ofrezco a leer', 'type' => 'OFFERING_AS_BETA_READER']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * La publicación llega al muro con su intención y su obra, que es lo que
     * permite filtrarla y pintar el enlace.
     */
    public function testThePostCarriesItsIntentionAndItsWork(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora);

        $this->publish($autora['token'], $workId);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $postId = (string) $this->payload()['postId'];
        $this->capture();
        $this->consumeEverything();

        $this->client->request('GET', '/api/v1/posts?scope=MINE_AND_FOLLOWED', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        $tarjetas = array_values(array_filter(
            $this->payload()['posts'],
            static fn (array $card): bool => $postId === $card['postId'],
        ));

        self::assertNotEmpty($tarjetas);
        self::assertSame('LOOKING_FOR_BETA_READERS', $tarjetas[0]['type']);
        self::assertSame($workId, $tarjetas[0]['workId']);
    }

    /**
     * @param array{token: string, userId: string} $author
     */
    private function aPublishedWork(array $author): string
    {
        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');
        $this->addChapter($workId, $author['token'], words: 900);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        return $workId;
    }

    private function publish(string $token, ?string $workId): void
    {
        $this->post($token, array_filter([
            'body' => 'Busco lectores beta para esto',
            'type' => 'LOOKING_FOR_BETA_READERS',
            'workId' => $workId,
        ]));
    }

    /**
     * @param array<string, string> $body
     */
    private function post(string $token, array $body): void
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        $this->capture();
    }
}
