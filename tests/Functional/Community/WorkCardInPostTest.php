<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Incluir un relato de la plataforma en una publicación (`FEAT-COM-028`).
 *
 * **La diferencia con un enlace externo es toda la ficha.** Un enlace guarda
 * una previsualización que envejece; esto guarda un `workId` y pinta la obra
 * con sus datos de ahora mismo. Si el autor le cambia el título, la
 * publicación de hace un mes enseña el nuevo.
 *
 * Y si la obra deja de ser visible —archivada, borrada o bloqueada por un
 * moderador— la tarjeta desaparece y **el texto de la publicación se queda**.
 * Esa asimetría es deliberada: el texto es de quien lo escribió, la obra es
 * de quien la escribió, y ninguno decide sobre lo del otro.
 */
final class WorkCardInPostTest extends EconomyScenario
{
    /**
     * `RN-1`, `RN-2`: la tarjeta viaja **dentro de la publicación**, resuelta,
     * y no como un identificador que el cliente tenga que ir a buscar.
     */
    public function testThePostCarriesTheWorkResolved(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora, 'La ciudad de los pájaros');

        $this->publish($autora['token'], 'Os enseño en lo que ando', $workId);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $card = $this->firstCard($autora['token']);

        self::assertSame($workId, $card['work']['workId']);
        self::assertSame('La ciudad de los pájaros', $card['work']['title']);
        self::assertSame($autora['userId'], $card['work']['authorId']);
        self::assertSame('PUBLISHED', $card['work']['status']);
        self::assertSame(1, $card['work']['chapterCount']);
        self::assertGreaterThan(0, $card['work']['readingMinutes']);
        self::assertSame($workId, $card['workId'], 'El identificador se queda: dice qué obra se citó.');
    }

    /**
     * `RN-3`: **es viva.** El autor renombra la obra y la publicación de
     * antes enseña el nombre nuevo, sin tocar la publicación.
     *
     * Es lo único que separa esto de cachear una previsualización, y por eso
     * la tarjeta se pide a `Work` en cada lectura en vez de proyectarse.
     */
    public function testTheCardFollowsTheWork(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora, 'Título viejo');

        $this->publish($autora['token'], 'Os enseño esto', $workId);

        $this->client->request('PATCH', \sprintf('/api/v1/works/%s', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['title' => 'Título nuevo'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertSame('Título nuevo', $this->firstCard($autora['token'])['work']['title']);
    }

    /**
     * `RN-4`: la obra se archiva. **La publicación sigue entera y la tarjeta
     * no está.**.
     *
     * Borrar la publicación sería dejar que el autor de la obra borrase el
     * texto de otra persona sin saberlo.
     */
    public function testArchivingTheWorkRemovesTheCardAndKeepsThePost(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora);

        $this->publish($autora['token'], 'Os enseño esto', $workId);

        $this->client->request('DELETE', \sprintf('/api/v1/works/%s', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['confirm' => true], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->consumeEverything();

        $card = $this->firstCard($autora['token']);

        self::assertNull($card['work'], 'La obra ya no está, así que no se anuncia.');
        self::assertSame('Os enseño esto', $card['body'], 'Pero el texto es de quien lo escribió.');
        self::assertSame($workId, $card['workId']);
    }

    /**
     * `RN-4`: un moderador bloquea la obra y **deja de anunciarse en el
     * acto**, para todo el mundo, sin que nadie tenga que tocar la
     * publicación.
     *
     * Es la razón de que la tarjeta sea síncrona: con una proyección
     * alimentada por la cola, la obra bloqueada seguiría en el muro con su
     * título y su sinopsis durante lo que tardara el mensaje.
     */
    public function testAWorkBlockedByModerationStopsBeingAdvertised(): void
    {
        $autora = $this->activatedPerson('autora');
        $moderadora = $this->moderator('moderadora');
        $workId = $this->aPublishedWork($autora);

        $this->publish($autora['token'], 'Os enseño esto', $workId);
        self::assertNotNull($this->firstCard($autora['token'])['work']);

        // Por donde se bloquea de verdad: alguien reclama y un moderador le
        // da la razón (`FEAT-MOD-002`, `FEAT-MOD-003`).
        $quienReclama = $this->activatedPerson('reclamante');

        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$quienReclama['token'],
        ], content: json_encode([
            'targetType' => 'WORK',
            'targetId' => $workId,
            'reason' => 'OFFENSIVE',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $claimId = (string) $this->payload()['claimId'];
        $this->capture();
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderadora['token'],
        ], content: json_encode([
            'decision' => 'UPHELD',
            'motivation' => 'La escena incumple las normas y no está etiquetada.',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertNull($this->firstCard($autora['token'])['work']);
    }

    /**
     * `RN-5`: una obra en borrador **no se anuncia a nadie más**, ni siquiera
     * desde la publicación de su propio autor.
     *
     * `FEAT-COM-003` ya impide reclutar lectores con un borrador; esto es lo
     * mismo por el otro lado: una publicación `GENERAL` sí puede citarlo, y
     * lo que no puede es enseñárselo a quien no puede abrirlo.
     */
    public function testADraftIsNotAdvertised(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'Todavía en borrador');
        $this->addChapter($workId, $autora['token'], words: 500);
        $this->consumeEverything();

        $this->publish($autora['token'], 'Estoy con esto', $workId);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertNull($this->firstCard($autora['token'])['work']);
    }

    /**
     * La tarjeta lleva **lo que la obra declara contener** (`FEAT-WRK-017`
     * `RN-8`): una advertencia que solo aparece cuando ya estás leyendo no
     * advierte de nada.
     */
    public function testTheCardCarriesTheContentWarnings(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora);

        $this->putAs(\sprintf('/api/v1/works/%s/content-rating', $workId), $autora['token'], [
            'adultsOnly' => true,
            'contentWarnings' => ['GRAPHIC_VIOLENCE'],
        ]);
        $this->consumeEverything();

        $this->publish($autora['token'], 'Aviso de lo que hay', $workId);

        $card = $this->firstCard($autora['token'])['work'];

        self::assertTrue($card['adultsOnly']);
        self::assertContains('GRAPHIC_VIOLENCE', $card['contentWarnings']);
    }

    /**
     * Una publicación sin obra no trae tarjeta, y una obra inventada tampoco
     * llega a crearse: `FEAT-COM-002` ya responde `404` antes.
     */
    public function testAPostWithoutAWorkHasNoCard(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->publish($autora['token'], 'Hoy no traigo nada', null);

        $card = $this->firstCard($autora['token']);

        self::assertNull($card['work']);
        self::assertNull($card['workId']);
    }

    /**
     * **Una consulta para toda la página, no una por tarjeta.**.
     *
     * No se mide aquí el número de consultas —eso sería atarse a la
     * implementación— sino lo que garantiza: un muro con varias obras
     * distintas las resuelve todas, cada una con lo suyo.
     */
    public function testAWallWithSeveralWorksResolvesThemAll(): void
    {
        $autora = $this->activatedPerson('autora');

        $primera = $this->aPublishedWork($autora, 'La primera');
        $this->publish($autora['token'], 'Una', $primera);

        $segunda = $this->aPublishedWork($autora, 'La segunda');
        $this->publish($autora['token'], 'Otra', $segunda);

        $this->wall($autora['token']);

        /** @var list<array<string, mixed>> $posts */
        $posts = $this->payload()['posts'];
        $titles = [];

        foreach ($posts as $post) {
            if (null !== $post['work']) {
                $titles[$post['work']['workId']] = $post['work']['title'];
            }
        }

        self::assertSame('La primera', $titles[$primera] ?? null);
        self::assertSame('La segunda', $titles[$segunda] ?? null);
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

    private function publish(string $token, string $body, ?string $workId): void
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(array_filter([
            'body' => $body,
            'type' => 'GENERAL',
            'workId' => $workId,
        ]), \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function wall(string $token): void
    {
        $this->client->request('GET', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @return array<string, mixed>
     */
    private function firstCard(string $token): array
    {
        $this->wall($token);

        /** @var list<array<string, mixed>> $posts */
        $posts = $this->payload()['posts'];
        self::assertNotEmpty($posts);

        return $posts[0];
    }
}
