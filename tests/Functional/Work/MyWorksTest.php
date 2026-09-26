<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Mis relatos» (`FEAT-WRK-015`).
 *
 * `RN-1` parece obvia y es la que hay que probar: un fallo aquí expone los
 * borradores de otro autor, que es obra inédita que nadie ha decidido
 * enseñar.
 */
final class MyWorksTest extends EconomyScenario
{
    public function testItListsOnlyYourOwnWorksIncludingDrafts(): void
    {
        $autora = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');

        $borrador = $this->createWork($autora['token'], 'En borrador');
        $this->addChapter($borrador, $autora['token'], words: 300);
        $publicada = $this->publishedWork($autora, 'Publicada');
        $this->publishedWork($otra, 'De otra persona');
        $this->consumeEverything();

        $this->mine($autora['token']);
        self::assertResponseIsSuccessful();

        $ids = array_column($this->payload()['works'], 'workId');
        sort($ids);
        $esperados = [$borrador, $publicada];
        sort($esperados);

        self::assertSame($esperados, $ids, 'Las suyas, borradores incluidos, y nada más.');
        self::assertSame(2, $this->payload()['total']);

        foreach ($this->payload()['works'] as $obra) {
            self::assertArrayNotHasKey('content', $obra, 'Metadatos, nunca contenido.');
            self::assertArrayHasKey('status', $obra, 'Con la insignia de estado, que aquí sí.');
        }
    }

    /**
     * `RN-2` y el hallazgo de la pantalla: los tres filtros suman el total.
     */
    public function testTheThreeStatusFiltersAddUpToTheTotal(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->createWork($autora['token'], 'Uno en borrador');
        $this->publishedWork($autora, 'Una visible');
        $enCorreccion = $this->publishedWork($autora, 'Una en corrección');
        $this->putAs(\sprintf('/api/v1/works/%s/status', $enCorreccion), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->mine($autora['token']);
        $total = $this->payload()['total'];

        $suma = 0;

        foreach (['DRAFT', 'PUBLISHED', 'IN_CORRECTION'] as $status) {
            $this->mine($autora['token'], ['status' => $status]);
            self::assertResponseIsSuccessful();
            $suma += $this->payload()['total'];
        }

        self::assertSame(3, $total);
        self::assertSame($total, $suma);
    }

    public function testItSortsByCreationDateInBothDirections(): void
    {
        $autora = $this->activatedPerson('autora');
        $primera = $this->createWork($autora['token'], 'La primera');
        $segunda = $this->createWork($autora['token'], 'La segunda');

        $this->mine($autora['token'], ['sort' => 'recent']);
        self::assertSame([$segunda, $primera], array_column($this->payload()['works'], 'workId'));

        $this->mine($autora['token'], ['sort' => 'oldest']);
        self::assertSame([$primera, $segunda], array_column($this->payload()['works'], 'workId'));
    }

    /**
     * Un `sort` desconocido que devuelve la lista en cualquier orden parece
     * funcionar, y el error solo se descubre cuando alguien se fía.
     */
    public function testAnUnsupportedSortIsRefusedInsteadOfIgnored(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->createWork($autora['token'], 'La única');

        $this->mine($autora['token'], ['sort' => 'mas-valorados']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNSUPPORTED_SORT', $this->payload()['code']);
        self::assertSame('recent,oldest,rated', $this->payload()['supportedSorts'], 'Y dice cuáles valen.');
    }

    public function testItPaginates(): void
    {
        $autora = $this->activatedPerson('autora');

        for ($numero = 1; $numero <= 3; ++$numero) {
            $this->createWork($autora['token'], \sprintf('Obra %d', $numero));
        }

        $this->mine($autora['token'], ['perPage' => '2']);
        self::assertCount(2, $this->payload()['works']);
        self::assertSame(3, $this->payload()['total']);
        self::assertSame(2, $this->payload()['totalPages']);

        $this->mine($autora['token'], ['perPage' => '2', 'page' => '2']);
        self::assertCount(1, $this->payload()['works']);
    }

    /**
     * Una obra retirada sigue estando aquí, marcada: si no, «recuperar» sería
     * una operación sin pantalla desde la que pedirla.
     */
    public function testAnArchivedWorkStaysInTheListMarked(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->publishedWork($autora, 'La retirada');

        $this->client->request('DELETE', \sprintf('/api/v1/works/%s', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['confirm' => true], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->mine($autora['token']);
        self::assertCount(1, $this->payload()['works']);
        self::assertTrue($this->payload()['works'][0]['archived']);
    }

    /**
     * `RN-5`: es solo lectura, así que la cuenta sin activar la usa.
     */
    public function testItWorksWithoutActivatingTheAccount(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->mine($token);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['works']);
    }

    public function testWithoutASessionThereIsNothing(): void
    {
        $this->client->request('GET', '/api/v1/me/works');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array<string, string> $query
     */
    private function mine(string $token, array $query = []): void
    {
        $this->client->request('GET', '/api/v1/me/works', $query, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
