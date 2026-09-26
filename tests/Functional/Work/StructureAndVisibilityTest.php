<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estructurar la obra (`FEAT-WRK-003`) y ocultar capítulos (`FEAT-WRK-008`).
 *
 * El orden no es cosmético: **el último capítulo es el que responde las
 * preguntas de la obra entera**, así que moverlo cambia lo que se pide y lo
 * que se paga. Y ocultar no es borrar: lo que alguien corrigió se conserva.
 */
final class StructureAndVisibilityTest extends EconomyScenario
{
    public function testAChapterCanBeInsertedInTheMiddle(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La novela');
        $this->titled($workId, $autora['token'], 'Uno');
        $this->titled($workId, $autora['token'], 'Tres');

        $this->titled($workId, $autora['token'], 'Dos', position: 2);

        self::assertSame(['Uno', 'Dos', 'Tres'], $this->titlesOf($workId, $autora['token']));
    }

    /**
     * `RN-2`: la lista entera, y se rechaza entera si no cuadra. Aplicar la
     * mitad deja la obra en un estado que el autor no pidió.
     */
    public function testReorderingTakesTheWholeListOrNothing(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La desordenada');
        $uno = $this->titled($workId, $autora['token'], 'Uno');
        $dos = $this->titled($workId, $autora['token'], 'Dos');
        $tres = $this->titled($workId, $autora['token'], 'Tres');

        $this->reorder($workId, $autora['token'], [$tres, $uno]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'Falta uno.');
        self::assertSame('CHAPTER_ORDER_INCOMPLETE', $this->payload()['code']);

        self::assertSame(['Uno', 'Dos', 'Tres'], $this->titlesOf($workId, $autora['token']), 'Y no se aplicó nada.');

        $this->reorder($workId, $autora['token'], [$tres, $dos, $uno]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(['Tres', 'Dos', 'Uno'], $this->titlesOf($workId, $autora['token']));
    }

    /**
     * `RN-5`: el capítulo que pasa a ser el último empieza a cobrar por las
     * preguntas de la obra entera, y el que deja de serlo deja de cobrarlas.
     */
    public function testReorderingRepricesTheChapterThatBecomesTheLast(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La que se reordena');
        $primero = $this->addChapter($workId, $autora['token'], words: 400, marker: 'Uno');
        $ultimo = $this->addChapter($workId, $autora['token'], words: 400, marker: 'Dos');
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Qué te ha parecido este capítulo?', 'minWords' => 20],
            ['statement' => '¿Y el final de la historia?', 'minWords' => 200, 'scope' => 'LAST_CHAPTER'],
        ]);
        $this->consumeEverything();

        $antesPrimero = $this->priceOf($primero);
        $antesUltimo = $this->priceOf($ultimo);
        self::assertGreaterThan($antesPrimero, $antesUltimo, 'El último cobra por la pregunta de obra entera.');

        $this->reorder($workId, $autora['token'], [$ultimo, $primero]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        self::assertSame($antesUltimo, $this->priceOf($primero), 'Ahora lo cobra el otro.');
        self::assertSame($antesPrimero, $this->priceOf($ultimo));
    }

    /**
     * `RN-6`: borrar un capítulo corregido destruiría el trabajo de quien lo
     * corrigió y el rastro de un cobro.
     */
    public function testAChapterSomebodyCorrectedIsNotRemovedButHidden(): void
    {
        [$autora, , , $workId] = $this->aDeliveredCorrection();

        $this->work($workId, $autora['token']);
        $chapterId = (string) $this->payload()['chapters'][0]['chapterId'];

        $this->remove($chapterId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CHAPTER_HAS_CORRECTIONS', $this->payload()['code']);

        $this->setVisibility($chapterId, $autora['token'], 'HIDDEN');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT, 'Ocultarlo sí.');
    }

    public function testRemovingAChapterNobodyReadRenumbersTheRest(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La que encoge');
        $uno = $this->titled($workId, $autora['token'], 'Uno');
        $this->titled($workId, $autora['token'], 'Dos');
        $this->titled($workId, $autora['token'], 'Tres');

        $this->remove($uno, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame(['Dos', 'Tres'], $this->titlesOf($workId, $autora['token']));

        $this->work($workId, $autora['token']);
        self::assertSame(600, $this->payload()['wordCount'], 'Y el recuento de la obra cuadra.');
        self::assertSame([1, 2], array_column($this->payload()['chapters'], 'position'));
    }

    /**
     * `RN-8`: en borrador se puede vaciar una obra; publicada, no.
     */
    public function testAPublishedWorkCannotBeLeftWithoutChapters(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La única');
        $unico = $this->titled($workId, $autora['token'], 'Uno');
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        $this->remove($unico, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('WORK_NEEDS_A_CHAPTER', $this->payload()['code']);

        // Y en una obra que nunca se publicó, sí: ahí no hay nadie leyendo.
        $borrador = $this->createWork($autora['token'], 'La que sigue en borrador');
        $suyo = $this->titled($borrador, $autora['token'], 'Uno');

        $this->remove($suyo, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * `FEAT-WRK-008` `RN-2`, `RN-3`: un capítulo oculto no existe para nadie
     * más, y no admite corrección.
     */
    public function testAHiddenChapterDisappearsForEverybodyButItsAuthor(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->createWork($autora['token'], 'La que se oculta');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 400);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Qué te ha parecido?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->chapter($chapterId, $lectora['token']);
        self::assertResponseIsSuccessful('Antes de ocultarlo se lee.');

        $this->setVisibility($chapterId, $autora['token'], 'HIDDEN');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->chapter($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Deja de existir para el resto.');

        $this->chapter($chapterId, $autora['token']);
        self::assertResponseIsSuccessful('Su autora lo sigue viendo.');

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Y no admite corrección.');

        $this->setVisibility($chapterId, $autora['token'], 'VISIBLE');
        $this->chapter($chapterId, $lectora['token']);
        self::assertResponseIsSuccessful('Volver a mostrarlo lo devuelve.');
    }

    /**
     * `RN-6`: ocultar un capítulo **descuenta sus palabras** del recuento de
     * la obra, y con ellas su tiempo de lectura.
     *
     * Lo que se enseña es lo que se puede leer. El número de capítulos no se
     * toca a propósito: un capítulo oculto sigue existiendo para su autora,
     * que es quien lo ve en «Mis relatos» y quien puede volver a mostrarlo.
     */
    public function testHidingAChapterTakesItsWordsOffTheWorksCount(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La que adelgaza');
        $this->addChapter($workId, $autora['token'], words: 300, marker: 'El que se queda');
        $ocultado = $this->addChapter($workId, $autora['token'], words: 500, marker: 'El que se oculta');

        self::assertSame(800, $this->wordCountOf($workId, $autora['token']));

        $this->setVisibility($ocultado, $autora['token'], 'HIDDEN');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        self::assertSame(300, $this->wordCountOf($workId, $autora['token']));
        self::assertCount(2, $this->titlesOf($workId, $autora['token']), 'Para su autora siguen siendo dos.');

        // Reescribir un capítulo oculto no lo devuelve al recuento.
        $this->client->request('PUT', \sprintf('/api/v1/chapters/%s', $ocultado), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode([
            'contentHtml' => '<p>'.implode(' ', array_fill(0, 900, 'palabra')).'</p>',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();

        self::assertSame(300, $this->wordCountOf($workId, $autora['token']), 'Sigue sin contar.');

        $this->setVisibility($ocultado, $autora['token'], 'VISIBLE');
        $this->capture();

        self::assertSame(1200, $this->wordCountOf($workId, $autora['token']), 'Y al volver, vuelve con lo que ahora tiene.');
    }

    public function testNobodyElseStructuresSomebodyElsesWork(): void
    {
        $autora = $this->activatedPerson('autora');
        $extrana = $this->activatedPerson('extrana');
        $workId = $this->createWork($autora['token'], 'La ajena');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 300);

        $this->reorder($workId, $extrana['token'], [$chapterId]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->remove($chapterId, $extrana['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->setVisibility($chapterId, $extrana['token'], 'HIDDEN');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function wordCountOf(string $workId, string $token): int
    {
        $this->work($workId, $token);

        return (int) $this->payload()['wordCount'];
    }

    private function titled(string $workId, string $token, string $title, ?int $position = null): string
    {
        $body = [
            'title' => $title,
            'content' => '<p>'.implode(' ', array_fill(0, 300, 'palabra')).'</p>',
        ];

        if (null !== $position) {
            $body['position'] = $position;
        }

        $this->client->request('POST', \sprintf('/api/v1/works/%s/chapters', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['chapterId'];
    }

    /**
     * @return list<string|null>
     */
    private function titlesOf(string $workId, string $token): array
    {
        $this->work($workId, $token);

        return array_column($this->payload()['chapters'], 'title');
    }

    private function priceOf(string $chapterId): int
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return (int) $connection->fetchOne(
            'SELECT price FROM credits_ctx.chapter_price WHERE chapter_id = :id',
            ['id' => $chapterId],
        );
    }

    /**
     * @param list<string> $order
     */
    private function reorder(string $workId, string $token, array $order): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/chapters/order', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['order' => $order], \JSON_THROW_ON_ERROR));
    }

    private function remove(string $chapterId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function setVisibility(string $chapterId, string $token, string $visibility): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/chapters/%s/visibility', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['visibility' => $visibility], \JSON_THROW_ON_ERROR));
    }

    private function work(string $workId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function chapter(string $chapterId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
