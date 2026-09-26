<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Work;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las temáticas de una obra (`FEAT-WRK-001`, y lo que desbloquea
 * `FEAT-WRK-012` `RN-5`).
 *
 * El catálogo de temáticas lo posee `User` —nació para el onboarding— y una
 * obra apunta a los mismos códigos. Que la lista esté en un solo sitio es lo
 * que hace que «Drama» signifique lo mismo cuando una persona dice lo que le
 * gusta leer y cuando un autor clasifica lo que ha escrito.
 */
final class WorkGenresTest extends EconomyScenario
{
    public function testAWorkIsBornClassifiedIfItsAuthorSaysSo(): void
    {
        $author = $this->activatedPerson('autora');

        $workId = $this->createWorkWith($author['token'], ['DRAMA', 'FANTASY']);

        self::assertSame(['DRAMA', 'FANTASY'], $this->genresOf($workId, $author['token']));
    }

    /**
     * La ficha dice que la temática es **opcional**, así que una obra sin
     * clasificar tiene que poder existir. Lo que le pasa es que no aparece
     * cuando alguien filtra, que es consecuencia suficiente.
     */
    public function testAWorkWithoutGenresIsPerfectlyLegal(): void
    {
        $author = $this->activatedPerson('autora');

        $workId = $this->createWork($author['token'], 'Sin clasificar');

        self::assertSame([], $this->genresOf($workId, $author['token']));
    }

    /**
     * Reclasificar es dejar la lista como el autor la ve, no ir apilando
     * etiquetas: el `PUT` sustituye.
     */
    public function testReclassifyingReplacesTheWholeList(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWorkWith($author['token'], ['DRAMA']);

        $this->setGenres($workId, $author['token'], ['FANTASY', 'ROMANCE']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame(['FANTASY', 'ROMANCE'], $this->genresOf($workId, $author['token']));

        // Y la lista vacía deja la obra sin clasificar, que es legítimo.
        $this->setGenres($workId, $author['token'], []);
        self::assertSame([], $this->genresOf($workId, $author['token']));
    }

    /**
     * **Reclasificar conservando una temática**, que es el caso normal: se
     * cambia una y se dejan las demás.
     *
     * Parece el mismo caso que el anterior y no lo es. Sustituir borrando
     * todo y volviendo a insertar funciona mientras las listas no se
     * solapen, y revienta en cuanto lo hacen: la fila conservada se borra y
     * se registra otra vez con la misma identidad en la misma transacción.
     */
    public function testReclassifyingCanKeepOneOfTheGenres(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWorkWith($author['token'], ['DRAMA', 'FANTASY']);

        $this->setGenres($workId, $author['token'], ['FANTASY', 'ROMANCE']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(['FANTASY', 'ROMANCE'], $this->genresOf($workId, $author['token']));
    }

    /**
     * Un código desconocido **se nombra**. Descartarlo en silencio dejaría la
     * obra clasificada de forma distinta a como su autor cree.
     */
    public function testAnUnknownGenreIsRefusedByName(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');

        $this->setGenres($workId, $author['token'], ['DRAMA', 'CIENCIA_FICCION_ESPACIAL']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_GENRE', $this->payload()['code']);
        self::assertStringContainsString('CIENCIA_FICCION_ESPACIAL', (string) $this->payload()['detail']);

        self::assertSame([], $this->genresOf($workId, $author['token']), 'No se guarda a medias.');
    }

    /**
     * El tope no es cosmético: el filtro del catálogo es en `O`, así que una
     * obra con ocho temáticas aparecería en casi cualquier búsqueda y el
     * filtro dejaría de filtrar.
     */
    public function testAWorkCannotBeEverything(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');

        $this->setGenres($workId, $author['token'], ['DRAMA', 'FANTASY', 'ROMANCE', 'ADVENTURE']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('TOO_MANY_GENRES', $this->payload()['code']);
    }

    public function testNobodyClassifiesSomebodyElsesWork(): void
    {
        $author = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');

        $workId = $this->createWork($author['token'], 'La ciudad de los pájaros');

        $this->setGenres($workId, $otra['token'], ['DRAMA']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * **`L-7` resuelta: en `O`.** Filtrar por «Drama» y «Fantasía» devuelve
     * las obras que son cualquiera de las dos. Con `Y`, añadir una temática
     * al filtro reduciría los resultados hasta dejar la pantalla vacía, que
     * es lo contrario de descubrir.
     */
    public function testTheCatalogueFiltersByAnyOfTheGenresAsked(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $drama = $this->published($author, 'Solo drama', ['DRAMA']);
        $fantasia = $this->published($author, 'Solo fantasía', ['FANTASY']);
        $ambas = $this->published($author, 'Las dos', ['DRAMA', 'FANTASY']);
        $this->published($author, 'Ni una ni otra', ['ROMANCE']);

        $encontradas = $this->catalogueIds($lectora['token'], ['genres' => ['DRAMA', 'FANTASY']]);

        self::assertCount(3, $encontradas, 'Cualquiera de las dos, no las dos.');
        self::assertContains($drama, $encontradas);
        self::assertContains($fantasia, $encontradas);
        self::assertContains($ambas, $encontradas, 'Y la que es ambas aparece **una vez**.');
    }

    public function testTheCatalogueCarriesTheGenresOfEachWork(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->published($author, 'La ciudad de los pájaros', ['DRAMA', 'FANTASY']);

        $this->catalogue($lectora['token']);

        self::assertSame(['DRAMA', 'FANTASY'], $this->payload()['works'][0]['genres']);
    }

    /**
     * Una temática que nadie tiene no es un error: es una búsqueda sin
     * resultados. No hay forma de distinguir un código inventado de uno
     * retirado del catálogo, y las obras que lo tuvieran siguen apuntando a
     * él.
     */
    public function testFilteringByAGenreNobodyHasIsJustAnEmptyList(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->catalogue($lectora['token'], ['genres' => ['LO_QUE_SEA']]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->payload()['total']);
    }

    /**
     * @param array{token: string, userId: string} $author
     * @param list<string>                         $genres
     */
    private function published(array $author, string $title, array $genres): string
    {
        $workId = $this->createWorkWith($author['token'], $genres, $title);
        $this->addChapter($workId, $author['token'], words: 600);

        $this->client->request('PUT', \sprintf('/api/v1/works/%s/status', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ], content: json_encode(['status' => 'PUBLISHED'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();

        return $workId;
    }

    /**
     * @param list<string> $genres
     */
    private function createWorkWith(string $token, array $genres, string $title = 'La ciudad de los pájaros'): string
    {
        $this->client->request('POST', '/api/v1/works', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['title' => $title, 'genres' => $genres], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['workId'];
    }

    /**
     * @param list<string> $genres
     */
    private function setGenres(string $workId, string $token, array $genres): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/genres', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['genres' => $genres], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function genresOf(string $workId, string $token): array
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        /** @var list<string> $genres */
        $genres = $this->payload()['genres'];

        return $genres;
    }

    /**
     * @param array<string, list<string>|string> $filters
     *
     * @return list<string>
     */
    private function catalogueIds(string $token, array $filters): array
    {
        $this->catalogue($token, $filters);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $works */
        $works = $this->payload()['works'];

        return array_map(static fn (array $work): string => (string) $work['workId'], $works);
    }

    /**
     * @param array<string, list<string>|string> $filters
     */
    private function catalogue(string $token, array $filters = []): void
    {
        $this->client->request('GET', '/api/v1/works?'.http_build_query($filters), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
