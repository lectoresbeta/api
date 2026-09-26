<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\AuthorPage\Domain\Service\AwardPolicy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los premios y reconocimientos del autor (`FEAT-USR-030`).
 *
 * `P-19` llevaba abierta desde que se leyeron las capturas de «Mi perfil»,
 * porque la sub-pestaña aparece nombrada y no hay ni una captura de su
 * interior. Se decidió que es **una ficha que declara el autor**, hermana de
 * la bibliografía y sin imagen.
 *
 * Lo que defienden estas pruebas es lo que esa decisión implica: que no se
 * valida nada, que lo único obligatorio es el título, que el orden lo pone el
 * año y no el autor, y que el enlace que escribe un usuario y pulsa cualquiera
 * no puede ser cualquier cosa.
 */
final class AuthorAwardsTest extends EconomyScenario
{
    /** `RN-4`: solo el título es obligatorio. */
    public function testATitleIsEnoughToDeclareAnAward(): void
    {
        $autora = $this->activatedPerson('autora');

        $award = $this->add($autora['token'], ['title' => 'Premio de relato corto de Getafe']);

        self::assertSame('Premio de relato corto de Getafe', $award['title']);
        self::assertNull($award['awardedBy']);
        self::assertNull($award['year']);
        self::assertNull($award['note']);
        self::assertNull($award['url']);
        self::assertFalse($award['urlIsExternal']);
    }

    /**
     * `RN-3`: **no se valida contra nada.** No existe un registro universal de
     * premios literarios, y el concurso del ayuntamiento y el Premio Planeta
     * se declaran igual. El enlace es lo que permite comprobarlo, no la
     * plataforma.
     */
    public function testWhoGrantedItIsFreeText(): void
    {
        $autora = $this->activatedPerson('autora');

        $award = $this->add($autora['token'], [
            'title' => 'Mención de honor',
            'awardedBy' => 'El taller de los martes',
            'year' => 2019,
            'note' => 'Categoría relato breve',
        ]);

        self::assertSame('El taller de los martes', $award['awardedBy']);
        self::assertSame(2019, $award['year']);
        self::assertSame('Categoría relato breve', $award['note']);
    }

    /** `RN-2`: es contenido público, y se lee **sin sesión**. */
    public function testTheAwardsArePublic(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->add($autora['token'], ['title' => 'Premio de la crítica']);

        $this->client->request('GET', '/api/v1/users/'.$autora['userId'].'/awards');

        self::assertResponseIsSuccessful();
        self::assertSame(['Premio de la crítica'], $this->titles());
    }

    /**
     * Pero la lista pregunta primero por el perfil. Si contestara por su
     * cuenta sería un camino lateral para confirmar que una cuenta existe
     * justo cuando su titular ha pedido que no se sepa (`FEAT-USR-038`).
     */
    public function testAClosedProfileHidesItsAwardsToo(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->add($autora['token'], ['title' => 'Premio de la crítica']);

        $this->putAs('/api/v1/me/privacy-settings', $autora['token'], ['profileVisibility' => 'NOBODY']);

        $this->client->request('GET', '/api/v1/users/'.$autora['userId'].'/awards');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Su titular se sigue viendo a sí mismo.
        $this->listAs($autora['userId'], $autora['token']);
        self::assertResponseIsSuccessful();
        self::assertSame(['Premio de la crítica'], $this->titles());
    }

    /**
     * `RN-8`, y es la única diferencia deliberada con la bibliografía: **el
     * orden lo pone el año descendente y el autor no lo toca**. Un historial
     * de premios se lee como un currículo, y una lista que se puede reordenar
     * es una lista donde el orden pasa a ser información.
     */
    public function testTheMostRecentComesFirstAndTheUndatedLast(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->add($autora['token'], ['title' => 'Sin fecha']);
        $this->add($autora['token'], ['title' => 'El viejo', 'year' => 2001]);
        $this->add($autora['token'], ['title' => 'El nuevo', 'year' => 2024]);

        $this->listAs($autora['userId'], $autora['token']);

        self::assertSame(['El nuevo', 'El viejo', 'Sin fecha'], $this->titles());
    }

    /**
     * Y no hay forma de reordenarlos: el campo no existe y el endpoint
     * tampoco.
     */
    public function testThereIsNoWayToReorderThem(): void
    {
        $autora = $this->activatedPerson('autora');
        $awardId = $this->add($autora['token'], ['title' => 'El único', 'year' => 2020])['awardId'];

        $award = $this->patch($autora['token'], (string) $awardId, ['position' => 0]);

        self::assertArrayNotHasKey('position', $award);
    }

    /** `RN-5`: el rango atrapa el error de teclado. */
    public function testAnImplausibleYearIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');

        foreach ([19, 1200, (int) date('Y') + 5] as $year) {
            $this->post($autora['token'], ['title' => 'Premio', 'year' => $year]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, (string) $year);
            self::assertSame('IMPLAUSIBLE_AWARD_YEAR', $this->payload()['code']);
        }
    }

    /**
     * **`RN-6`, y es la regla de seguridad de esta ficha.** El enlace lo
     * escribe un usuario y lo pulsa cualquiera que abra su perfil: un
     * `javascript:` convertiría lo que parece un enlace en algo que ejecuta lo
     * que escribió un extraño.
     */
    public function testOnlyHttpLinksAreAccepted(): void
    {
        $autora = $this->activatedPerson('autora');

        foreach (['javascript:alert(1)', 'data:text/html,<script>', 'file:///etc/passwd', 'no-es-un-enlace'] as $url) {
            $this->post($autora['token'], ['title' => 'Premio', 'url' => $url]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, $url);
            self::assertSame('INVALID_AWARD_URL', $this->payload()['code']);
        }

        $award = $this->add($autora['token'], ['title' => 'Premio', 'url' => 'https://ayuntamiento.example/fallo']);
        self::assertSame('https://ayuntamiento.example/fallo', $award['url']);
        self::assertTrue($award['urlIsExternal'], 'El cliente tiene que saber que sale de la plataforma.');
    }

    public function testATitleIsNotOptional(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->post($autora['token'], ['title' => '   ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('VALIDATION_FAILED', $this->payload()['code']);
    }

    /**
     * `PATCH` distingue «no lo toques» de «déjalo en blanco» por la presencia
     * de la clave, no por su valor.
     */
    public function testWhatIsNotSentStaysAndAnExplicitNullClears(): void
    {
        $autora = $this->activatedPerson('autora');
        $awardId = (string) $this->add($autora['token'], [
            'title' => 'Premio',
            'awardedBy' => 'El taller',
            'year' => 2019,
            'note' => 'Finalista',
        ])['awardId'];

        $award = $this->patch($autora['token'], $awardId, ['year' => 2020]);

        self::assertSame(2020, $award['year']);
        self::assertSame('El taller', $award['awardedBy'], 'Lo que no se envía se queda.');

        $award = $this->patch($autora['token'], $awardId, ['note' => null]);
        self::assertNull($award['note']);
        self::assertSame('Premio', $award['title']);
    }

    /** `RN-1`: solo su titular lo gestiona, y se le dice. */
    public function testSomebodyElsesAwardIsNotYoursToTouch(): void
    {
        $autora = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $awardId = (string) $this->add($autora['token'], ['title' => 'Premio'])['awardId'];

        $this->client->request('DELETE', '/api/v1/me/awards/'.$awardId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$otra['token'],
        ]);

        self::assertResponseStatusCodeSame(
            Response::HTTP_FORBIDDEN,
            '403 y no 404: el premio se enseña en un perfil abierto, y fingir que no está sería mentirle a quien lo acaba de ver.',
        );
        self::assertSame('NOT_YOUR_AWARD', $this->payload()['code']);
    }

    public function testAnAwardThatDoesNotExistSaysSo(): void
    {
        $autora = $this->activatedPerson('autora');

        foreach (['0192b1f0-0000-7000-8000-000000000000', 'no-es-un-uuid'] as $awardId) {
            $this->client->request('DELETE', '/api/v1/me/awards/'.$awardId, server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $awardId);
            self::assertSame('AWARD_NOT_FOUND', $this->payload()['code']);
        }
    }

    public function testDeletingOneLeavesTheRest(): void
    {
        $autora = $this->activatedPerson('autora');
        $awardId = (string) $this->add($autora['token'], ['title' => 'El que sobra', 'year' => 2020])['awardId'];
        $this->add($autora['token'], ['title' => 'El que queda', 'year' => 2019]);

        $this->client->request('DELETE', '/api/v1/me/awards/'.$awardId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->listAs($autora['userId'], $autora['token']);
        self::assertSame(['El que queda'], $this->titles());
    }

    /** `RN-7`: un perfil no es un palmarés interminable. */
    public function testThereIsACeiling(): void
    {
        $autora = $this->activatedPerson('autora');

        for ($i = 0; $i < AwardPolicy::MAX_PER_AUTHOR; ++$i) {
            $this->add($autora['token'], ['title' => 'Premio '.$i]);
        }

        $this->post($autora['token'], ['title' => 'Uno de más']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('TOO_MANY_AWARDS', $this->payload()['code']);
    }

    /**
     * `RN-9`: esto no mueve créditos, no cuenta como relato y no le interesa a
     * ningún otro contexto. Un evento «por si acaso» es un contrato que luego
     * hay que mantener.
     */
    public function testDeclaringAnAwardAnnouncesNothing(): void
    {
        $autora = $this->activatedPerson('autora');
        $before = $this->balanceOf($autora['userId']);

        $this->capture();
        $this->add($autora['token'], ['title' => 'Premio', 'year' => 2020]);

        self::assertSame([], $this->capture());
        self::assertSame($before, $this->balanceOf($autora['userId']));
    }

    public function testWithoutASessionNothingIsDeclared(): void
    {
        $this->client->request('POST', '/api/v1/me/awards', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['title' => 'Premio'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function add(string $token, array $body): array
    {
        $this->post($token, $body);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->payload();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $token, array $body): void
    {
        $this->client->request('POST', '/api/v1/me/awards', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function patch(string $token, string $awardId, array $body): array
    {
        $this->client->request('PATCH', '/api/v1/me/awards/'.$awardId, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    private function listAs(string $userId, string $token): void
    {
        $this->client->request('GET', '/api/v1/users/'.$userId.'/awards', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @return list<string>
     */
    private function titles(): array
    {
        /** @var list<array<string, mixed>> $awards */
        $awards = $this->payload()['awards'];

        return array_map(static fn (array $award): string => (string) $award['title'], $awards);
    }
}
