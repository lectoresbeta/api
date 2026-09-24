<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La sección «Leer» (`FEAT-WRK-012`).
 *
 * Lo que se defiende aquí es lo que hace distinto a este catálogo: **ordena
 * por reparto de trabajo, no por popularidad**
 * ([`decision:0008`](../../../docs/decisions/0008-catalogue-ordering.md)), y
 * los dos tests que esa decisión exige explícitamente —que una obra que
 * recibe una corrección baja, y que lo no corregible no ocupa sitio— están
 * aquí.
 */
final class CatalogueTest extends EconomyScenario
{
    public function testTheCatalogueShowsWhatCanBeReadAndNeverADraft(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $publicada = $this->aWork($author, 'Publicada', open: false);
        $borrador = $this->createWork($author['token'], 'En el cajón');

        $this->catalogue($lectora['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->payload()['total']);
        self::assertSame($publicada, $this->payload()['works'][0]['workId']);
        self::assertStringNotContainsString($borrador, json_encode($this->payload(), \JSON_THROW_ON_ERROR));
    }

    /**
     * `RN-7`: nadie corrige lo suyo, así que ocuparían sitio en la única
     * pantalla donde se busca trabajo ajeno.
     */
    public function testYourOwnWorksAreNotInYourCatalogue(): void
    {
        $author = $this->activatedPerson('autora');
        $this->aWork($author, 'La ciudad de los pájaros');

        $this->catalogue($author['token']);

        self::assertSame(0, $this->payload()['total']);
    }

    /**
     * **El test que `decision:0008` pide por su nombre.** Una obra que recibe
     * una corrección baja de posición: es la propiedad que hace que el
     * reparto se sostenga solo, porque aparecer arriba consume lo que te puso
     * arriba.
     */
    public function testAWorkThatReceivesACorrectionGoesDown(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $primera = $this->aWork($author, 'Primera');
        $segunda = $this->aWork($author, 'Segunda');

        $this->consumeEverything();

        $orden = $this->orderOfTitles($lectora['token']);
        self::assertCount(2, $orden);

        // Se corrige la que va delante.
        $delante = $this->payload()['works'][0]['workId'];
        $this->correct($delante === $primera ? $primera : $segunda, $lectora);

        $this->consumeEverything();

        $despues = $this->orderOfTitles($lectora['token']);

        self::assertNotSame($orden, $despues, 'Recibir una corrección baja a la obra.');
        self::assertSame(1, $this->payload()['works'][1]['correctionsReceived']);
    }

    /**
     * El otro test que la decisión pide: **un capítulo que no se puede
     * corregir no ocupa el sitio más valioso de la pantalla**. Sigue en el
     * catálogo —se puede leer— pero al final.
     */
    public function testWhatCannotBeCorrectedDoesNotTakeTheTopSpot(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $soloLectura = $this->aWork($author, 'Solo se lee', open: false);
        $corregible = $this->aWork($author, 'Se puede corregir');

        $this->consumeEverything();
        $this->catalogue($lectora['token']);

        self::assertSame(2, $this->payload()['total']);
        self::assertSame($corregible, $this->payload()['works'][0]['workId']);
        self::assertSame($soloLectura, $this->payload()['works'][1]['workId']);
        self::assertSame(0, $this->payload()['works'][1]['correctableChapters']);

        // El cero gris del diseño no dice que corregirla sea gratis: dice
        // que ahora mismo no se puede (`FEAT-CRD-013`).
        self::assertSame(0, $this->payload()['works'][1]['credits']);
        self::assertGreaterThan(0, $this->payload()['works'][0]['credits']);
    }

    /**
     * **`RN-2` de `FEAT-CRD-013`, que es la razón de ser de la insignia:** la
     * cifra de la tarjeta y la que después se abona salen de la misma regla.
     * Prometer seis y abonar quince es peor que no enseñar nada.
     */
    public function testTheBadgeIsExactlyWhatTheCorrectorIsLaterPaid(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $obra = $this->aWork($author, 'La ciudad de los pájaros');
        $this->consumeEverything();

        $this->catalogue($lectora['token']);
        $insignia = $this->payload()['works'][0]['credits'];
        self::assertGreaterThan(0, $insignia);

        $antes = $this->balanceOf($lectora['userId']);
        $this->correct($obra, $lectora);
        $this->consumeEverything();

        self::assertSame($insignia, $this->balanceOf($lectora['userId']) - $antes);
    }

    /**
     * `RN-2`: un filtro de la interfaz no es una autorización. `DRAFT` se
     * rechaza **aunque se fuerce el parámetro**, y no se ignora: devolver los
     * resultados de otra consulta es peor, porque nadie lo nota.
     */
    public function testForcingDraftIntoTheFilterIsRefused(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->catalogue($lectora['token'], ['status' => 'DRAFT']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_FILTER_VALUE', $this->payload()['code']);

        $this->catalogue($lectora['token'], ['status' => 'LO_QUE_SEA']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->catalogue($lectora['token'], ['sort' => 'popularidad']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testTheStatusFilterNarrowsTheTotal(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->aWork($author, 'Publicada', open: false);
        $this->aWork($author, 'En corrección');

        $this->catalogue($lectora['token'], ['status' => 'IN_CORRECTION']);

        self::assertSame(1, $this->payload()['total'], 'El total refleja los filtros, no el catálogo entero.');
        self::assertSame('IN_CORRECTION', $this->payload()['works'][0]['status']);
    }

    /**
     * Páginas numeradas y con total, que es la excepción justificada a la
     * convención de cursor (`L-4`): quien busca quiere saber cuántos hay y
     * saltar a la página 4.
     */
    public function testThePagesAreNumberedAndTheTotalIsReal(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        for ($i = 0; $i < 3; ++$i) {
            $this->aWork($author, 'Obra '.$i, open: false);
        }

        $this->catalogue($lectora['token'], ['perPage' => '2']);

        self::assertSame(3, $this->payload()['total']);
        self::assertSame(2, $this->payload()['totalPages']);
        self::assertCount(2, $this->payload()['works']);

        $this->catalogue($lectora['token'], ['perPage' => '2', 'page' => '2']);
        self::assertCount(1, $this->payload()['works']);

        // Una página fuera de rango no es un error: es una lista vacía.
        $this->catalogue($lectora['token'], ['perPage' => '2', 'page' => '9']);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['works']);
    }

    public function testAnEmptyCatalogueIsNotAnError(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->catalogue($lectora['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->payload()['total']);
        self::assertSame(0, $this->payload()['totalPages']);
        self::assertSame([], $this->payload()['works']);
    }

    public function testTheCatalogueShowsMetadataAndNeverContent(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->aWork($author, 'La ciudad de los pájaros');
        $this->catalogue($lectora['token']);

        $card = $this->payload()['works'][0];

        self::assertSame(
            ['workId', 'title', 'synopsis', 'status', 'wordCount', 'chapterCount', 'adultsOnly', 'contentWarnings', 'genres', 'correctableChapters', 'credits', 'correctionsReceived'],
            array_keys($card),
        );
        self::assertStringNotContainsString('palabra palabra', json_encode($card, \JSON_THROW_ON_ERROR));
    }

    /**
     * Sin sesión no hay catálogo. Abrirlo después es compatible; cerrarlo,
     * no (`L-8`).
     */
    public function testWithoutASessionThereIsNoCatalogue(): void
    {
        $this->client->request('GET', '/api/v1/works');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array{token: string, userId: string} $author
     */
    private function aWork(array $author, string $title, bool $open = true): string
    {
        $workId = $this->createWork($author['token'], $title);
        $this->addChapter($workId, $author['token'], words: 1200);
        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);

        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $author['token'], ['accessMode' => 'PUBLIC']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);

        if ($open) {
            $this->put(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'IN_CORRECTION']);
        }

        return $workId;
    }

    /**
     * @param array{token: string, userId: string} $reader
     */
    private function correct(string $workId, array $reader): void
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$reader['token'],
        ]);
        self::assertResponseIsSuccessful();

        $chapterId = (string) $this->payload()['chapters'][0]['chapterId'];

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$reader['token'],
        ]);
        self::assertResponseIsSuccessful();

        $questionId = (string) $this->payload()['questions'][0]['questionId'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$reader['token'],
        ], content: json_encode([
            'answers' => [['questionId' => $questionId, 'text' => implode(' ', array_fill(0, 60, 'palabra'))]],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
    }

    /**
     * @return list<string>
     */
    private function orderOfTitles(string $token): array
    {
        $this->catalogue($token);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $works */
        $works = $this->payload()['works'];

        return array_map(static fn (array $work): string => (string) $work['workId'], $works);
    }

    /**
     * @param array<string, string> $filters
     */
    private function catalogue(string $token, array $filters = []): void
    {
        $this->client->request('GET', '/api/v1/works?'.http_build_query($filters), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param array<string, string> $body
     */
    private function put(string $path, string $token, array $body): void
    {
        $this->client->request('PUT', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }
}
