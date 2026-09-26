<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filtrar y buscar en el muro (`FEAT-COM-009`).
 *
 * **Se filtra por intención, no por formato.** `docs/ui/home.md` dejaba la
 * duda —una publicación tiene las dos dimensiones— y la respuesta está en
 * para qué sirve cada una: la intención dice qué quiere quien publica, que es
 * lo que alguien busca; el formato dice si llevaba una foto. Nadie entra al
 * muro a buscar publicaciones con imagen.
 *
 * La búsqueda de texto es **full-text de PostgreSQL en español**, no un
 * `ILIKE`. La diferencia se ve en una prueba de aquí: «escribir» encuentra
 * «escribiendo», porque busca por raíz.
 *
 * Y lo que más importa: **filtrar no abre nada**. Los filtros se aplican
 * dentro de la misma consulta y después de la visibilidad, así que una
 * publicación que no te alcanza no aparece por mucho que la busques por su
 * texto exacto.
 */
final class WallSearchTest extends EconomyScenario
{
    /**
     * `RN-1`: por intención.
     */
    public function testFilteringByIntention(): void
    {
        $autora = $this->activatedPerson('autora');

        $general = $this->publish($autora['token'], 'Hoy he escrito mil palabras');
        $buddy = $this->publish($autora['token'], 'Busco compañía', type: 'LOOKING_FOR_WRITING_BUDDY');

        $encontrado = $this->search($autora['token'], '?type=LOOKING_FOR_WRITING_BUDDY');

        self::assertContains($buddy, $encontrado);
        self::assertNotContains($general, $encontrado);
    }

    /**
     * `RN-2`: por texto, **y por raíz**.
     *
     * Es la prueba que justifica el índice: con un `ILIKE '%escribir%'` esto
     * no encontraría nada, porque en el texto pone «escribiendo».
     */
    public function testSearchingMatchesTheStemNotTheSubstring(): void
    {
        $autora = $this->activatedPerson('autora');

        $encontrable = $this->publish($autora['token'], 'Llevo toda la tarde escribiendo el tercer capítulo');
        $otra = $this->publish($autora['token'], 'Me he pasado el día leyendo');

        $encontrado = $this->search($autora['token'], '?q=escribir');

        self::assertContains($encontrable, $encontrado);
        self::assertNotContains($otra, $encontrado);
    }

    /**
     * Y **sin tropezar con lo que la gente escribe de verdad**: apóstrofos,
     * paréntesis y signos que harían reventar a `to_tsquery`.
     */
    public function testSearchingSurvivesWhatPeopleActuallyType(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->publish($autora['token'], 'Una novela sobre el mar');

        $this->client->request('GET', '/api/v1/posts?q='.urlencode("novela (& | ')"), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * `RN-3`: por quien **escribió**.
     */
    public function testFilteringByAuthor(): void
    {
        $una = $this->activatedPerson('una');
        $otra = $this->activatedPerson('otra');

        $suya = $this->publish($una['token'], 'Lo de una');
        $ajena = $this->publish($otra['token'], 'Lo de otra');

        $encontrado = $this->search($una['token'], '?authorId='.$otra['userId']);

        self::assertContains($ajena, $encontrado);
        self::assertNotContains($suya, $encontrado);
    }

    /**
     * `RN-4`: por fechas, con los dos extremos incluidos.
     */
    public function testFilteringByDateRange(): void
    {
        $autora = $this->activatedPerson('autora');
        $hoy = $this->publish($autora['token'], 'Lo de hoy');

        $manana = (new \DateTimeImmutable('+1 day'))->format('Y-m-d');
        $ayer = (new \DateTimeImmutable('-1 day'))->format('Y-m-d');

        self::assertContains($hoy, $this->search($autora['token'], '?from='.$ayer));
        self::assertNotContains($hoy, $this->search($autora['token'], '?from='.$manana));
        self::assertNotContains($hoy, $this->search($autora['token'], '?to='.$ayer));
    }

    /**
     * `RN-5`: los filtros **se combinan con Y**. Cada uno estrecha lo
     * anterior, que es lo que espera cualquiera que haya usado un buscador.
     */
    public function testFiltersNarrowEachOther(): void
    {
        $una = $this->activatedPerson('una');
        $otra = $this->activatedPerson('otra');

        $coincide = $this->publish($una['token'], 'Busco quien escriba conmigo', type: 'LOOKING_FOR_WRITING_BUDDY');
        $mismoTexto = $this->publish($otra['token'], 'Busco quien escriba conmigo', type: 'LOOKING_FOR_WRITING_BUDDY');
        $mismaPersona = $this->publish($una['token'], 'Busco quien escriba conmigo');

        $encontrado = $this->search(
            $una['token'],
            '?type=LOOKING_FOR_WRITING_BUDDY&q=escribir&authorId='.$una['userId'],
        );

        self::assertContains($coincide, $encontrado);
        self::assertNotContains($mismoTexto, $encontrado, 'Coincide el texto y el tipo, pero no la persona.');
        self::assertNotContains($mismaPersona, $encontrado, 'Coinciden persona y texto, pero no el tipo.');
    }

    /**
     * **Filtrar no abre nada.** Es la prueba más importante del fichero.
     *
     * Una publicación `FOLLOWERS` de alguien a quien no sigues no aparece ni
     * buscándola por su texto exacto. Los filtros van dentro de la misma
     * consulta y después de la visibilidad: si fueran un paso aparte sobre un
     * conjunto ya traído, o peor, una consulta propia, este sería el agujero.
     */
    public function testSearchingDoesNotReachWhatTheWallHides(): void
    {
        $yo = $this->activatedPerson('yo');
        $otra = $this->activatedPerson('otra');

        $restringida = $this->publish($otra['token'], 'Un secreto irrepetible', audience: 'FOLLOWERS');

        self::assertNotContains($restringida, $this->search($yo['token'], '?q=irrepetible'));

        $this->follow($yo['token'], $otra['userId']);

        self::assertContains(
            $restringida,
            $this->search($yo['token'], '?q=irrepetible'),
            'Y aparece en cuanto la audiencia la alcanza, no por haberla buscado.',
        );
    }

    /**
     * Ni lo de alguien con quien hay un bloqueo.
     */
    public function testSearchingDoesNotReachAcrossABlock(): void
    {
        $yo = $this->activatedPerson('yo');
        $otra = $this->activatedPerson('otra');

        $suya = $this->publish($otra['token'], 'Un texto irrepetible');

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $otra['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$yo['token'],
        ]);
        $this->capture();
        $this->consumeEverything();

        self::assertNotContains($suya, $this->search($yo['token'], '?q=irrepetible'));
    }

    /**
     * `RN-6`: los filtros también valen en el muro de una persona
     * (`FEAT-COM-026`). Es la misma consulta, así que sale gratis.
     */
    public function testTheFiltersWorkOnAPersonsWallToo(): void
    {
        $autora = $this->activatedPerson('autora');

        $general = $this->publish($autora['token'], 'Algo general');
        $buddy = $this->publish($autora['token'], 'Busco compañía', type: 'LOOKING_FOR_WRITING_BUDDY');

        $encontrado = $this->ids('/api/v1/me/posts?type=LOOKING_FOR_WRITING_BUDDY', $autora['token']);

        self::assertContains($buddy, $encontrado);
        self::assertNotContains($general, $encontrado);
    }

    /**
     * `RN-7`: **la paginación sobrevive al filtro.** Se filtra en la consulta
     * y no en memoria, así que una página de dos trae dos.
     */
    public function testAFilteredWallStillPaginates(): void
    {
        $autora = $this->activatedPerson('autora');

        for ($i = 0; $i < 3; ++$i) {
            $this->publish($autora['token'], 'Busco compañía '.$i, type: 'LOOKING_FOR_WRITING_BUDDY');
            $this->publish($autora['token'], 'Ruido que no debería contar '.$i);
        }

        $this->client->request('GET', '/api/v1/posts?type=LOOKING_FOR_WRITING_BUDDY&limit=2', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        $primera = $this->payload();
        self::assertCount(2, $primera['posts'], 'Dos de las que coinciden, no dos de las primeras que hay.');
        self::assertTrue($primera['pageInfo']['hasNextPage']);
    }

    /**
     * `RN-8`: una fecha mal escrita **se rechaza**. Devolver el muro entero
     * porque alguien escribió mal un día le haría creer lo contrario de lo
     * que ve.
     */
    public function testAMalformedFilterIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->client->request('GET', '/api/v1/posts?from=el-martes-pasado', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->client->request('GET', '/api/v1/posts?type=NO_EXISTE', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Y un rango que acaba antes de empezar, que no es un error de escritura
     * sino una petición imposible.
     */
    public function testARangeThatEndsBeforeItStartsIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->client->request('GET', '/api/v1/posts?from=2026-05-01&to=2026-04-01', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Sin filtros, el muro entero: pedir la lista sin acotar nada no puede
     * costar una condición.
     */
    public function testWithoutFiltersTheWallIsUnchanged(): void
    {
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Cualquier cosa');

        self::assertContains($postId, $this->search($autora['token'], ''));
    }

    private function publish(
        string $token,
        string $body,
        string $type = 'GENERAL',
        string $audience = 'EVERYONE',
    ): string {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'body' => $body,
            'type' => $type,
            'audience' => $audience,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    private function follow(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    /**
     * @return list<string>
     */
    private function search(string $token, string $query): array
    {
        return $this->ids('/api/v1/posts'.$query, $token);
    }

    /**
     * @return list<string>
     */
    private function ids(string $path, string $token): array
    {
        $this->client->request('GET', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();

        return array_values(array_map(
            static fn (array $post): string => (string) $post['postId'],
            $this->payload()['posts'],
        ));
    }
}
