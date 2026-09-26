<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Reading;

use LectoresBeta\Reading\AccessInvitation\Application\Handler\SearchInvitableReadersHandler;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Buscar a quién invitar (`FEAT-RDG-006`).
 *
 * [`FEAT-RDG-004`](../../../docs/features/reading/FEAT-RDG-004-invite-beta-reader.md) invita
 * por identificador y no dijo de dónde sale. Sale de aquí, y sin esto invitar
 * existía pero no se podía usar.
 *
 * Lo que se defiende son dos cosas distintas: que el descarte funcione —no
 * enseñar a quien después no se podría invitar— y que **el directorio no se
 * pueda recorrer**, que es la parte que protege a las personas buscadas.
 */
final class InvitableReadersTest extends EconomyScenario
{
    public function testTheAuthorFindsSomebodyByName(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->named('lectora', 'Ana García');
        $workId = $this->aWork($author);

        $this->search($workId, $author['token'], 'García');

        self::assertResponseIsSuccessful();
        self::assertSame([$lectora['userId']], $this->found());
    }

    public function testTheAuthorFindsSomebodyByUsername(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->named('lectora', 'Ana García');
        $workId = $this->aWork($author);

        $this->search($workId, $author['token'], substr($lectora['username'], 0, 4));

        self::assertResponseIsSuccessful();
        self::assertContains($lectora['userId'], $this->found());
    }

    /**
     * **`RN-2`, y es la regla que protege a quien no está buscando nada.** Un
     * buscador que acepta una dirección de correo responde sin querer a otra
     * pregunta —«¿esta persona tiene cuenta aquí?»— y se convierte en un
     * comprobador de direcciones para cualquiera con una lista.
     */
    public function testAnEmailAddressDoesNotFindItsOwner(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->named('lectora', 'Ana García');
        $workId = $this->aWork($author);

        $this->search($workId, $author['token'], $this->address('lectora'));

        self::assertResponseIsSuccessful();
        self::assertNotContains($lectora['userId'], $this->found());
    }

    /**
     * `RN-3`: sin mínimo de caracteres, la consulta corta devuelve el
     * directorio. Y no es un error — el cliente la manda en cada pulsación.
     */
    public function testAQueryTooShortReturnsNobodyRatherThanEverybody(): void
    {
        $author = $this->activatedPerson('autora');
        $this->named('lectora', 'Ana García');
        $workId = $this->aWork($author);

        foreach (['', 'a'] as $query) {
            $this->search($workId, $author['token'], $query);

            self::assertResponseIsSuccessful($query);
            self::assertSame([], $this->found());
        }
    }

    public function testTheAuthorNeverFindsThemselves(): void
    {
        $author = $this->named('autora', 'Ana García');
        $workId = $this->aWork($author);

        $this->search($workId, $author['token'], 'García');

        self::assertSame([], $this->found());
    }

    /**
     * `RN-6`: las tres razones por las que alguien ya no se puede invitar son
     * las mismas tres que el endpoint de invitar rechaza. Enseñar a alguien
     * para después negar su invitación sería peor que no enseñarlo.
     */
    public function testWhoeverIsAlreadyInDoesNotAppear(): void
    {
        $author = $this->activatedPerson('autora');
        $dentro = $this->named('dentro', 'Ana Dentro');
        $invitada = $this->named('invitada', 'Ana Invitada');
        $solicitante = $this->named('solicita', 'Ana Solicita');
        $libre = $this->named('libre', 'Ana Libre');

        $workId = $this->aWork($author);

        // Ya es lectora beta.
        $this->resolveInvitation($this->invite($workId, $author['token'], $dentro['userId']), $dentro['token']);

        // Tiene una invitación abierta.
        $invitationId = $this->invite($workId, $author['token'], $invitada['userId']);

        // Tiene una solicitud abierta.
        $this->accessMode($workId, $author['token'], 'ON_REQUEST');
        $this->ask($workId, $solicitante['token']);

        $this->search($workId, $author['token'], 'Ana');

        self::assertSame([$libre['userId']], $this->found(), 'Solo queda quien no está dentro de nada.');

        // Y quien deja de estarlo vuelve a aparecer.
        $this->cancelInvitation($invitationId, $author['token']);

        $this->search($workId, $author['token'], 'Ana');
        self::assertContains($invitada['userId'], $this->found());
    }

    /**
     * `RN-5`: solo cuentas activas. Una cuenta sin activar todavía no es
     * nadie a quien invitar —no podría aceptar— y una eliminada está
     * anonimizada, así que no le queda nada con lo que coincidir.
     */
    public function testAnAccountThatIsNotActiveDoesNotAppear(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->aWork($author);

        $sinActivar = $this->signedInWithoutActivating('pendiente');

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$sinActivar,
        ], content: json_encode(['name' => 'Ana Pendiente', 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->search($workId, $author['token'], 'Pendiente');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->found());
    }

    public function testNobodySearchesOverSomebodyElsesWork(): void
    {
        $author = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $workId = $this->aWork($author);

        $this->search($workId, $otra['token'], 'Ana');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `RN-7`: una tarjeta de perfil y nada más. Quien pregunta está dibujando
     * una fila de un desplegable, no consultando a una persona.
     */
    public function testARowCarriesAProfileCardAndNothingElse(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->named('lectora', 'Ana García');
        $workId = $this->aWork($author);

        $this->search($workId, $author['token'], 'García');

        $row = $this->payload()['readers'][0];

        self::assertSame(['userId', 'username', 'name', 'avatarUrl'], array_keys($row));
        self::assertSame('Ana García', $row['name']);
        self::assertSame($lectora['userId'], $row['userId']);
        self::assertStringNotContainsString('@', json_encode($row, \JSON_THROW_ON_ERROR));
    }

    /**
     * **`RN-4`: el directorio no se puede recorrer.** No hay cursor, no hay
     * total y el tope es duro. Con paginación, veinte peticiones devolverían
     * a todo el mundo igual, solo que más despacio.
     */
    public function testTheAnswerIsCappedAndThereIsNoWayToAskForMore(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->aWork($author);

        for ($i = 0; $i <= SearchInvitableReadersHandler::LIMIT; ++$i) {
            $this->named('lectora'.$i, 'Ana Repetida '.$i);
        }

        $this->search($workId, $author['token'], 'Repetida');

        self::assertCount(SearchInvitableReadersHandler::LIMIT, $this->payload()['readers']);
        self::assertArrayNotHasKey('pageInfo', $this->payload());
        self::assertArrayNotHasKey('total', $this->payload());

        // Y pedir más no sirve de nada: el parámetro no existe.
        $this->client->request(
            'GET',
            \sprintf('/api/v1/works/%s/invitable-readers?query=Repetida&limit=100&cursor=x', $workId),
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$author['token']],
        );

        self::assertResponseIsSuccessful();
        self::assertCount(SearchInvitableReadersHandler::LIMIT, $this->payload()['readers']);
    }

    public function testWithoutASessionThereIsNothing(): void
    {
        $this->client->request('GET', '/api/v1/works/x/invitable-readers?query=Ana');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Alguien con nombre declarado, que es lo que busca esta pantalla.
     *
     * @return array{token: string, userId: string, username: string}
     */
    private function named(string $local, string $name): array
    {
        $person = $this->activatedPerson($local);

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => $name, 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->client->request('GET', '/api/v1/me/onboarding', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ]);
        self::assertResponseIsSuccessful();

        return [...$person, 'username' => (string) $this->payload()['username']];
    }

    /**
     * @param array{token: string, userId: string} $author
     */
    private function aWork(array $author): string
    {
        $workId = $this->createWork($author['token'], 'La obra privada');
        $this->addChapter($workId, $author['token'], words: 900);
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');
        $this->accessMode($workId, $author['token'], 'PRIVATE');

        return $workId;
    }

    private function search(string $workId, string $token, string $query): void
    {
        $this->client->request(
            'GET',
            \sprintf('/api/v1/works/%s/invitable-readers?%s', $workId, http_build_query(['query' => $query])),
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );
    }

    /**
     * @return list<string>
     */
    private function found(): array
    {
        /** @var list<array<string, mixed>> $readers */
        $readers = $this->payload()['readers'];

        return array_map(static fn (array $reader): string => (string) $reader['userId'], $readers);
    }

    private function invite(string $workId, string $token, string $userId): string
    {
        $this->client->request('POST', \sprintf('/api/v1/works/%s/beta-reader-invitations', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['userId' => $userId], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['invitationId'];
    }

    private function resolveInvitation(string $invitationId, string $token): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/beta-reader-invitations/%s/resolution', $invitationId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => 'ACCEPTED'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    private function cancelInvitation(string $invitationId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/beta-reader-invitations/%s', $invitationId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function ask(string $workId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/works/%s/access-requests', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
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

    private function accessMode(string $workId, string $token, string $mode): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/access-mode', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['accessMode' => $mode], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }
}
