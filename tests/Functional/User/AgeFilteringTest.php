<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El filtro de edad (`FEAT-USR-044`).
 *
 * No es una preferencia: una obra `ADULTS_ONLY` no se sirve a quien no es
 * mayor de edad, y el usuario no lo desactiva. Lo que sí elige son las
 * preferencias de contenido sensible, que son otra cosa
 * ([`FEAT-USR-043`](../../../docs/features/user/FEAT-USR-043-content-preferences.md)).
 *
 * **La regla, en una frase: quien no ha declarado fecha de nacimiento no es
 * mayor de edad.** Tratar lo no declarado como adulto convertiría un paso
 * opcional del registro en la forma de saltarse el filtro, y sería además la
 * más fácil: no contestar.
 *
 * Lo que defiende esta prueba, y la razón de que las puertas se recorran
 * juntas en un solo caso, es que el filtro **no se olvide en una**. Un agujero aquí no se
 * nota: la operación responde correctamente, con contenido que alguien no
 * debería estar viendo. Cuando aparezca una pantalla nueva que sirva obras,
 * esta lista es lo que obliga a acordarse.
 */
final class AgeFilteringTest extends EconomyScenario
{
    /**
     * Todas las puertas a la vez, para las dos personas con sesión que no
     * pasan el filtro: la menor y la que no ha declarado nada. Quien no tiene
     * sesión va aparte, porque su respuesta es otra.
     *
     * El `404` en vez del `403` es el criterio general de la plataforma: si
     * alguien no debería saber siquiera que el recurso existe, `404`. Un
     * `403` que dijera «esto es para adultos» sería un índice de qué obras
     * lo son.
     */
    public function testNobodyUnderageReachesAnAdultsOnlyWorkByAnyDoor(): void
    {
        $author = $this->adult('autora');
        $menor = $this->minor('menor');
        $sinDeclarar = $this->activatedPerson('callada');

        $obra = $this->adultsOnlyWork($author);

        foreach ([
            'una menor de edad' => $menor['token'],
            'quien no ha declarado su fecha de nacimiento' => $sinDeclarar['token'],
        ] as $quien => $token) {
            foreach ($this->doorsFor($obra, $token) as $puerta => $respuesta) {
                self::assertSame(
                    Response::HTTP_NOT_FOUND,
                    $respuesta,
                    \sprintf('%s: se sirve a %s.', $puerta, $quien),
                );
            }

            self::assertNotContains(
                $obra['workId'],
                $this->cataloguedFor($token),
                \sprintf('El catálogo enseña la obra a %s.', $quien),
            );
        }
    }

    /**
     * `RN-3`: quien no tiene sesión tampoco es mayor de edad.
     *
     * Hoy la respuesta es más rotunda que un `404` —ninguna de las seis
     * puertas tiene superficie anónima— y por eso se afirma así y no «alguna
     * de las dos»: el día que una la tenga, esta prueba falla y obliga a
     * decidir qué se sirve sin sesión. Que es exactamente lo que hay que
     * decidir en `U-23`, los enlaces públicos.
     */
    public function testAVisitorWithoutASessionGetsNoneOfThoseDoorsAtAll(): void
    {
        $author = $this->adult('autora');
        $obra = $this->adultsOnlyWork($author);

        foreach ($this->doorsFor($obra, null) as $puerta => $respuesta) {
            self::assertSame(Response::HTTP_UNAUTHORIZED, $respuesta, \sprintf('%s: responde a un visitante.', $puerta));
        }

        self::assertSame([], $this->cataloguedFor(null));
    }

    /**
     * El control, y no es un adorno: sin él la prueba anterior pasaría con
     * una obra que **nadie** puede abrir, que es exactamente el fallo que
     * dejaría el filtro sin vigilar.
     */
    public function testAnAdultGoesThroughEveryOneOfThoseDoors(): void
    {
        $author = $this->adult('autora');
        $lectora = $this->adult('lectora');

        $obra = $this->adultsOnlyWork($author);

        foreach ($this->doorsFor($obra, $lectora['token']) as $puerta => $respuesta) {
            self::assertNotSame(Response::HTTP_NOT_FOUND, $respuesta, \sprintf('%s: no se sirve a una adulta.', $puerta));
        }

        self::assertContains($obra['workId'], $this->cataloguedFor($lectora['token']));
    }

    /**
     * `RN-6`, y es la excepción que tiene que existir: el autor está mirando
     * lo que ha escrito él. Su edad no le separa de su propio texto.
     */
    public function testTheAuthorAlwaysSeesTheirOwnWorkHoweverOldTheyAre(): void
    {
        $author = $this->minor('autora');
        $obra = $this->adultsOnlyWork($author);

        self::assertSame(
            Response::HTTP_OK,
            $this->statusOf('GET', \sprintf('/api/v1/works/%s', $obra['workId']), $author['token']),
        );

        self::assertSame(
            Response::HTTP_OK,
            $this->statusOf('GET', \sprintf('/api/v1/chapters/%s', $obra['chapterId']), $author['token']),
        );
    }

    /**
     * El control: sin él, esta prueba pasaría con un catálogo roto que no
     * enseña nada a nadie.
     */
    public function testAnAdultReadsItNormally(): void
    {
        $author = $this->adult('autora');
        $lectora = $this->adult('lectora');

        $obra = $this->adultsOnlyWork($author);

        self::assertContains($obra['workId'], $this->cataloguedFor($lectora['token']));

        self::assertSame(
            Response::HTTP_OK,
            $this->statusOf('GET', \sprintf('/api/v1/works/%s', $obra['workId']), $lectora['token']),
        );
    }

    /**
     * `U-22`: se comprueba **al invitar**, no solo al leer.
     *
     * Antes la invitación se cursaba y la lectura fallaba después, así que la
     * autora veía a alguien aceptar y no poder entrar, sin ninguna
     * explicación.
     *
     * Y el rechazo **no dice por qué**: la edad de otra persona no es asunto
     * de quien invita, y un mensaje que lo insinuara convertiría el botón de
     * invitar en un comprobador de quién es menor.
     */
    public function testInvitingSomebodyUnderageIsRefusedAtTheMomentOfInviting(): void
    {
        $author = $this->adult('autora');
        $menor = $this->minor('menor');

        $obra = $this->adultsOnlyWork($author);

        $this->client->request('POST', \sprintf('/api/v1/works/%s/beta-reader-invitations', $obra['workId']), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ], content: json_encode(['userId' => $menor['userId']], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('READER_CANNOT_SEE_THIS_WORK', $this->payload()['code']);
        self::assertStringNotContainsStringIgnoringCase('age', (string) $this->payload()['detail']);
        self::assertStringNotContainsStringIgnoringCase('edad', (string) $this->payload()['detail']);
    }

    /**
     * `RN-8`: reclasificar retira la obra **de inmediato**, incluido a quien
     * ya tenía acceso concedido. La clasificación puede llegar tarde —se
     * corrige, o la revisa moderación— y lo que no puede es quedar por
     * debajo de lo que ya se dio.
     */
    public function testReclassifyingTakesTheWorkAwayFromABetaReaderWhoAlreadyHadAccess(): void
    {
        $author = $this->adult('autora');
        $menor = $this->minor('menor');

        $obra = $this->adultsOnlyWork($author, adultsOnly: false);

        $this->client->request('POST', \sprintf('/api/v1/works/%s/beta-reader-invitations', $obra['workId']), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ], content: json_encode(['userId' => $menor['userId']], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $invitacion = (string) $this->payload()['invitationId'];
        $this->capture();

        $this->client->request('PUT', \sprintf('/api/v1/beta-reader-invitations/%s/resolution', $invitacion), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$menor['token'],
        ], content: json_encode(['decision' => 'ACCEPTED'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->consumeEverything();

        self::assertSame(
            Response::HTTP_OK,
            $this->statusOf('GET', \sprintf('/api/v1/works/%s', $obra['workId']), $menor['token']),
            'Hasta aquí la lectora beta entra.',
        );

        $this->classify($obra['workId'], $author['token'], adultsOnly: true);

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $this->statusOf('GET', \sprintf('/api/v1/works/%s', $obra['workId']), $menor['token']),
            'Y desde que la obra es para adultos, deja de entrar.',
        );
    }

    /**
     * Una obra publicada, con capítulo y cuestionario, abierta a corrección.
     *
     * @param array{token: string, userId: string} $author
     *
     * @return array{workId: string, chapterId: string}
     */
    private function adultsOnlyWork(array $author, bool $adultsOnly = true): array
    {
        $workId = $this->createWork($author['token'], 'Lo que no es para todos');
        $chapterId = $this->addChapter($workId, $author['token'], words: 900);

        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);

        $this->classify($workId, $author['token'], $adultsOnly);
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/access-mode', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ], content: json_encode(['accessMode' => 'PUBLIC'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();

        $this->changeStatus($workId, $author['token'], 'PUBLISHED');
        $this->changeStatus($workId, $author['token'], 'IN_CORRECTION');
        $this->consumeEverything();

        return ['workId' => $workId, 'chapterId' => $chapterId];
    }

    private function classify(string $workId, string $token, bool $adultsOnly): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/content-rating', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'adultsOnly' => $adultsOnly,
            'contentWarnings' => [],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->consumeEverything();
    }

    private function changeStatus(string $workId, string $token, string $status): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/status', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['status' => $status], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    /**
     * @return list<string>
     */
    private function cataloguedFor(?string $token): array
    {
        $this->client->request('GET', '/api/v1/works', server: null === $token ? [] : [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        if (null === $token) {
            // El catálogo entero pide sesión, así que para un visitante no
            // hay nada que filtrar: no hay lista.
            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

            return [];
        }

        self::assertResponseIsSuccessful();

        return array_values(array_map(
            static fn (array $work): string => (string) $work['workId'],
            $this->payload()['works'],
        ));
    }

    /**
     * Las seis puertas por las que se llega a una obra, en una sola llamada.
     *
     * Están juntas a propósito: un filtro que se aplica en cinco sitios y se
     * olvida en el sexto no se nota —la operación responde correctamente, con
     * contenido que alguien no debería estar viendo— y esta lista es lo que
     * obliga a acordarse cuando aparezca la séptima.
     *
     * @param array{workId: string, chapterId: string} $obra
     *
     * @return array<string, int>
     */
    private function doorsFor(array $obra, ?string $token): array
    {
        return [
            'Abrir la obra' => $this->statusOf('GET', \sprintf('/api/v1/works/%s', $obra['workId']), $token),
            'Leer el capítulo' => $this->statusOf('GET', \sprintf('/api/v1/chapters/%s', $obra['chapterId']), $token),
            'El cuestionario de la obra' => $this->statusOf('GET', \sprintf('/api/v1/works/%s/questionnaire', $obra['workId']), $token),
            'El cuestionario del capítulo' => $this->statusOf('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $obra['chapterId']), $token),
            'Empezar una corrección' => $this->statusOf('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $obra['chapterId']), $token),
            'Pedir acceso de lectora beta' => $this->statusOf('POST', \sprintf('/api/v1/works/%s/access-requests', $obra['workId']), $token, []),
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function statusOf(string $method, string $path, ?string $token, ?array $body = null): int
    {
        $server = null === $token ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$token];

        if (null !== $body) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $this->client->request($method, $path, server: $server, content: null === $body
            ? null
            : json_encode($body, \JSON_THROW_ON_ERROR));

        $this->capture();

        return $this->client->getResponse()->getStatusCode();
    }

    /**
     * @return array{token: string, userId: string}
     */
    private function adult(string $local): array
    {
        return $this->born($local, '1990-05-17');
    }

    /**
     * @return array{token: string, userId: string}
     */
    private function minor(string $local): array
    {
        return $this->born($local, (new \DateTimeImmutable('-14 years'))->format('Y-m-d'));
    }

    /**
     * @return array{token: string, userId: string}
     */
    private function born(string $local, string $birthDate): array
    {
        $person = $this->activatedPerson($local);

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => 'Ana '.$local, 'birthDate' => $birthDate], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        return $person;
    }
}
