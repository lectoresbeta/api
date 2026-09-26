<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Reading;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La agenda del autor (`FEAT-RDG-007`).
 *
 * Dos afirmaciones aguantan toda la ficha y son las que se defienden aquí:
 *
 * - **un grupo no concede nada** (`RN-11`, cierra `R-4`). Meter a alguien en
 *   una lista no le abre ninguna obra, y sacarlo no le cierra ninguna;
 * - **invitar en bloque son invitaciones normales** (`RN-12`, cierra `R-15`).
 *   Las mismas reglas, el mismo evento, y un resultado parcial descrito en
 *   lugar de un fallo total.
 */
final class BetaReaderGroupTest extends EconomyScenario
{
    public function testTheAuthorKeepsAListAndPutsPeopleInIt(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $groupId = $this->createGroup($author['token'], 'Los del taller');
        $this->addMember($groupId, $author['token'], $lectora['userId']);

        $this->get('/api/v1/beta-reader-groups/'.$groupId, $author['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('Los del taller', $this->payload()['name']);
        self::assertSame(1, $this->payload()['memberCount']);
        self::assertSame($lectora['userId'], $this->payload()['members'][0]['userId']);
    }

    /**
     * `RN-11`, y es **la** regla de esta ficha. El grupo es una agenda, no un
     * permiso: si pertenecer concediera acceso, habría un cuarto camino de
     * entrada a una obra y sería el más silencioso de todos.
     */
    public function testBeingInAGroupOpensNoWork(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->aPrivateWork($author);

        $groupId = $this->createGroup($author['token'], 'Los del taller');
        $this->addMember($groupId, $author['token'], $lectora['userId']);

        $this->get('/api/v1/works/'.$workId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'La obra privada sigue sin existir para quien solo está en la agenda.');
    }

    /**
     * El reverso: sacar a alguien de la lista **no le quita el acceso** que ya
     * tenía. Confundirlas convertiría editar una lista privada en echar a
     * alguien de una obra sin decírselo.
     */
    public function testLeavingTheGroupTakesNoAccessAway(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->aPrivateWork($author);

        $groupId = $this->createGroup($author['token'], 'Los del taller');
        $this->addMember($groupId, $author['token'], $lectora['userId']);
        $this->inviteGroup($workId, $author['token'], $groupId);
        $this->acceptTheOnlyInvitation($lectora['token']);

        $this->removeMember($groupId, $author['token'], $lectora['userId']);

        $this->get('/api/v1/works/'.$workId, $lectora['token']);
        self::assertResponseIsSuccessful('Sigue siendo lectora beta: el acceso lo dio la invitación, no la lista.');
    }

    /**
     * `RN-12`, cierra `R-15`. Una invitación por miembro, con todas sus
     * reglas: lo que ahorra el grupo es el clic.
     */
    public function testInvitingAGroupCreatesOneInvitationPerMember(): void
    {
        $author = $this->activatedPerson('autora');
        $una = $this->activatedPerson('una');
        $otra = $this->activatedPerson('otra');
        $workId = $this->aPrivateWork($author);

        $groupId = $this->createGroup($author['token'], 'Los del taller');
        $this->addMember($groupId, $author['token'], $una['userId']);
        $this->addMember($groupId, $author['token'], $otra['userId']);

        $this->inviteGroup($workId, $author['token'], $groupId);

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing([$una['userId'], $otra['userId']], $this->payload()['invited']);
        self::assertSame([], $this->payload()['skipped']);

        $this->get('/api/v1/works/'.$workId.'/beta-reader-invitations', $author['token']);
        self::assertCount(2, $this->payload()['invitations']);
    }

    /**
     * Y publica el mismo hecho que la invitación individual, una vez por
     * persona. Sin esto, invitar en bloque sería un camino por el que los
     * avisos no salen.
     */
    public function testEachInvitationAnnouncesItself(): void
    {
        $author = $this->activatedPerson('autora');
        $una = $this->activatedPerson('una');
        $otra = $this->activatedPerson('otra');
        $workId = $this->aPrivateWork($author);

        $groupId = $this->createGroup($author['token'], 'Los del taller');
        $this->addMember($groupId, $author['token'], $una['userId']);
        $this->addMember($groupId, $author['token'], $otra['userId']);

        $this->inviteGroup($workId, $author['token'], $groupId);

        self::assertCount(2, $this->announced('BetaReaderInvited'));
    }

    /**
     * **El resultado es parcial y descrito** (`RN-12`). Devolver `422` porque
     * uno de tres no se puede invitar dejaría al autor sin los dos que sí, y
     * sin saber por qué.
     */
    public function testWhoeverCannotBeInvitedIsSkippedWithItsReason(): void
    {
        $author = $this->activatedPerson('autora');
        $libre = $this->activatedPerson('libre');
        $yaInvitada = $this->activatedPerson('invitada');
        $workId = $this->aPrivateWork($author);

        $groupId = $this->createGroup($author['token'], 'Los del taller');
        $this->addMember($groupId, $author['token'], $libre['userId']);
        $this->addMember($groupId, $author['token'], $yaInvitada['userId']);

        // Una de ellas ya tiene su invitación, cursada a mano.
        $this->inviteOne($workId, $author['token'], $yaInvitada['userId']);

        $this->inviteGroup($workId, $author['token'], $groupId);

        self::assertResponseIsSuccessful();
        self::assertSame([$libre['userId']], $this->payload()['invited']);
        self::assertSame(
            [['userId' => $yaInvitada['userId'], 'reason' => 'INVITATION_ALREADY_PENDING']],
            $this->payload()['skipped'],
        );
    }

    /**
     * El motivo que viaja es **el mismo código** que habría devuelto la
     * invitación individual. Un vocabulario distinto para el caso en bloque
     * obligaría al cliente a conocer dos.
     */
    public function testAClosedMailboxIsSkippedAndSaysSo(): void
    {
        $author = $this->activatedPerson('autora');
        $cerrada = $this->activatedPerson('cerrada');
        $workId = $this->aPrivateWork($author);

        $this->putAs('/api/v1/me/reception-settings', $cerrada['token'], ['betaReaderInvitations' => false]);

        $groupId = $this->createGroup($author['token'], 'Los del taller');
        $this->addMember($groupId, $author['token'], $cerrada['userId']);

        $this->inviteGroup($workId, $author['token'], $groupId);

        self::assertSame([], $this->payload()['invited']);
        self::assertSame('INVITATIONS_NOT_ACCEPTED', $this->payload()['skipped'][0]['reason']);
    }

    /**
     * Un grupo vacío no invita a nadie, y **lo dice**: `200` con las dos
     * listas vacías, no un error.
     */
    public function testAnEmptyGroupInvitesNobodyAndDoesNotFail(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->aPrivateWork($author);

        $groupId = $this->createGroup($author['token'], 'Vacío');
        $this->inviteGroup($workId, $author['token'], $groupId);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['invited']);
        self::assertSame([], $this->payload()['skipped']);
    }

    /**
     * Y con el grupo vacío la obra **se mira igual**. Si la comprobación
     * viviera solo dentro de cada invitación, invitar un grupo vacío a la
     * obra de otro respondería `200`.
     */
    public function testAnEmptyGroupStillCannotReachSomebodyElsesWork(): void
    {
        $author = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $workId = $this->aPrivateWork($otra);

        $groupId = $this->createGroup($author['token'], 'Vacío');
        $this->inviteGroup($workId, $author['token'], $groupId, expected: Response::HTTP_NOT_FOUND);
    }

    /** `RN-3`: dos listas con el mismo nombre no se pueden elegir. */
    public function testTwoGroupsOfTheSameAuthorDoNotShareAName(): void
    {
        $author = $this->activatedPerson('autora');
        $this->createGroup($author['token'], 'Los del taller');

        $this->post('/api/v1/me/beta-reader-groups', $author['token'], ['name' => 'los DEL taller']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('GROUP_NAME_ALREADY_USED', $this->payload()['code']);

        // Pero el nombre de otra persona no estorba.
        $otra = $this->activatedPerson('otra');
        $this->createGroup($otra['token'], 'Los del taller');
    }

    public function testRenamingToItsOwnNameIsNotAClash(): void
    {
        $author = $this->activatedPerson('autora');
        $groupId = $this->createGroup($author['token'], 'Los del taller');

        $this->client->request('PATCH', '/api/v1/beta-reader-groups/'.$groupId, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ], content: json_encode(['name' => 'LOS DEL TALLER'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame('LOS DEL TALLER', $this->payload()['name']);
    }

    public function testANamelessGroupIsRefused(): void
    {
        $author = $this->activatedPerson('autora');

        $this->post('/api/v1/me/beta-reader-groups', $author['token'], ['name' => '   ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('GROUP_NAME_REQUIRED', $this->payload()['code']);
    }

    public function testTheAuthorIsNotInTheirOwnListOfBetaReaders(): void
    {
        $author = $this->activatedPerson('autora');
        $groupId = $this->createGroup($author['token'], 'Los del taller');

        $this->client->request('PUT', \sprintf('/api/v1/beta-reader-groups/%s/members/%s', $groupId, $author['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('AUTHOR_CANNOT_BE_A_MEMBER', $this->payload()['code']);
    }

    public function testSomebodyWhoDoesNotExistIsNotAMember(): void
    {
        $author = $this->activatedPerson('autora');
        $groupId = $this->createGroup($author['token'], 'Los del taller');

        $this->client->request('PUT', \sprintf('/api/v1/beta-reader-groups/%s/members/%s', $groupId, '0192b1f0-0000-7000-8000-000000000000'), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('USER_NOT_FOUND', $this->payload()['code']);
    }

    /** `RN-8` y `RN-9`: poner a quien ya está y quitar a quien no está. */
    public function testAddingAndRemovingAreIdempotent(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $groupId = $this->createGroup($author['token'], 'Los del taller');

        $this->addMember($groupId, $author['token'], $lectora['userId']);
        $this->addMember($groupId, $author['token'], $lectora['userId']);

        $this->get('/api/v1/beta-reader-groups/'.$groupId, $author['token']);
        self::assertSame(1, $this->payload()['memberCount']);

        $this->removeMember($groupId, $author['token'], $lectora['userId']);
        $this->removeMember($groupId, $author['token'], $lectora['userId']);

        $this->get('/api/v1/beta-reader-groups/'.$groupId, $author['token']);
        self::assertSame(0, $this->payload()['memberCount']);
    }

    /**
     * `RN-1`: el grupo ajeno responde igual que uno inexistente. Un grupo es
     * una anotación privada sobre otras personas, y confirmar que existe ya
     * diría quién tiene apuntado a quién.
     */
    public function testSomebodyElsesGroupDoesNotExist(): void
    {
        $author = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $groupId = $this->createGroup($author['token'], 'Los del taller');

        foreach ([
            ['GET', '/api/v1/beta-reader-groups/'.$groupId],
            ['DELETE', '/api/v1/beta-reader-groups/'.$groupId],
            ['PUT', \sprintf('/api/v1/beta-reader-groups/%s/members/%s', $groupId, $author['userId'])],
        ] as [$method, $path]) {
            $this->client->request($method, $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$otra['token']]);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, $method.' '.$path);
            self::assertSame('GROUP_NOT_FOUND', $this->payload()['code']);
        }

        // Y un identificador que ni siquiera es un identificador responde lo
        // mismo, no un `500`.
        $this->get('/api/v1/beta-reader-groups/no-es-un-uuid', $author['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /** `RN-10`: la lista se va y las invitaciones que produjo se quedan. */
    public function testDeletingAGroupLeavesItsInvitationsAlone(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->aPrivateWork($author);

        $groupId = $this->createGroup($author['token'], 'Los del taller');
        $this->addMember($groupId, $author['token'], $lectora['userId']);
        $this->inviteGroup($workId, $author['token'], $groupId);

        $this->client->request('DELETE', '/api/v1/beta-reader-groups/'.$groupId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$author['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->get('/api/v1/works/'.$workId.'/beta-reader-invitations', $author['token']);
        self::assertCount(1, $this->payload()['invitations']);

        $this->get('/api/v1/me/beta-reader-groups', $author['token']);
        self::assertSame([], $this->payload()['groups']);
    }

    /** `RN-13`, cierra `R-20`: buscar entre los propios grupos, por nombre. */
    public function testTheAuthorSearchesAmongTheirOwnGroups(): void
    {
        $author = $this->activatedPerson('autora');
        $this->createGroup($author['token'], 'Novela negra');
        $this->createGroup($author['token'], 'Los del taller');

        $this->get('/api/v1/me/beta-reader-groups?query=NEGRA', $author['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(['Novela negra'], $this->names());

        // Sin consulta salen todos, y por nombre.
        $this->get('/api/v1/me/beta-reader-groups', $author['token']);
        self::assertSame(['Los del taller', 'Novela negra'], $this->names());

        // Un comodín de SQL es un carácter más, no un comodín.
        $this->get('/api/v1/me/beta-reader-groups?query=%25', $author['token']);
        self::assertSame([], $this->names());
    }

    public function testMyGroupsAreOnlyMine(): void
    {
        $author = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $this->createGroup($author['token'], 'Los del taller');

        $this->get('/api/v1/me/beta-reader-groups', $otra['token']);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['groups']);
    }

    public function testWithoutASessionThereIsNothing(): void
    {
        $this->client->request('GET', '/api/v1/me/beta-reader-groups');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array{token: string, userId: string} $author
     */
    private function aPrivateWork(array $author): string
    {
        $workId = $this->createWork($author['token'], 'La obra privada');
        $this->addChapter($workId, $author['token'], words: 900);

        $this->putAs('/api/v1/works/'.$workId.'/status', $author['token'], ['status' => 'PUBLISHED']);
        $this->putAs('/api/v1/works/'.$workId.'/access-mode', $author['token'], ['accessMode' => 'PRIVATE']);
        $this->capture();

        return $workId;
    }

    private function createGroup(string $token, string $name): string
    {
        $this->post('/api/v1/me/beta-reader-groups', $token, ['name' => $name]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['groupId'];
    }

    private function addMember(string $groupId, string $token, string $readerId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/beta-reader-groups/%s/members/%s', $groupId, $readerId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function removeMember(string $groupId, string $token, string $readerId): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/beta-reader-groups/%s/members/%s', $groupId, $readerId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function inviteGroup(string $workId, string $token, string $groupId, int $expected = Response::HTTP_OK): void
    {
        $this->post('/api/v1/works/'.$workId.'/group-invitations', $token, ['groupId' => $groupId]);

        self::assertResponseStatusCodeSame($expected);
        $this->capture();
    }

    private function inviteOne(string $workId, string $token, string $userId): void
    {
        $this->post('/api/v1/works/'.$workId.'/beta-reader-invitations', $token, ['userId' => $userId]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
    }

    private function acceptTheOnlyInvitation(string $token): void
    {
        $this->get('/api/v1/me/beta-reader-invitations', $token);
        self::assertResponseIsSuccessful();

        $invitationId = (string) $this->payload()['invitations'][0]['invitationId'];

        $this->putAs('/api/v1/beta-reader-invitations/'.$invitationId.'/resolution', $token, ['decision' => 'ACCEPTED']);
        $this->capture();
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        /** @var list<array<string, mixed>> $groups */
        $groups = $this->payload()['groups'];

        return array_map(static fn (array $group): string => (string) $group['name'], $groups);
    }

    private function get(string $path, string $token): void
    {
        $this->client->request('GET', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, string $token, array $body): void
    {
        $this->client->request('POST', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }
}
