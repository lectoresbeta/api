<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Reading;

use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\Reading\BetaReaderAccess\Application\Contract\BetaReaderAccessCheck;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El camino `PRIVATE`: invitar y que te contesten (`FEAT-RDG-004`,
 * `FEAT-RDG-005`).
 *
 * Es el único camino que empieza por el autor, y el que cierra el reparto:
 * hasta ahora una obra `PRIVATE` no admitía a nadie.
 *
 * Lo que se defiende aquí son las dos asimetrías que la ficha eligió a
 * conciencia —invitar vale en cualquier modalidad aunque solicitar no, y
 * rechazar sí se cuenta aunque cancelar no— y el caso raro que las justifica:
 * **invitar a un borrador**.
 */
final class BetaReaderInvitationTest extends EconomyScenario
{
    public function testBeingInvitedAndAcceptingMakesYouABetaReader(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $invitationId = $this->invite($workId, $author['token'], $lectora['userId'], 'Te invito a leerla.');
        self::assertFalse($this->isBetaReader($workId, $lectora['userId']), 'Invitar no concede nada.');
        self::assertSame($lectora['userId'], $this->lastAnnouncementOf('BetaReaderInvited')['readerId']);

        $this->resolve($invitationId, $lectora['token'], 'ACCEPTED');

        self::assertResponseIsSuccessful();
        self::assertSame('ACCEPTED', $this->payload()['status']);

        // Sin consumir ninguna cola: los dos agregados son de `Reading`.
        self::assertTrue($this->isBetaReader($workId, $lectora['userId']));
    }

    /**
     * Lo que hace que el camino sirva de algo: una obra `PRIVATE` no existe
     * para nadie más hasta que su autor abre la puerta.
     */
    public function testOnceAcceptedTheReaderCanOpenAPrivateWork(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $this->openWork($workId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->resolve($this->invite($workId, $author['token'], $lectora['userId']), $lectora['token'], 'ACCEPTED');

        $this->openWork($workId, $lectora['token']);
        self::assertResponseIsSuccessful();
    }

    /**
     * `RN-6` de `FEAT-RDG-005`, y la asimetría con cancelar una solicitud:
     * **el rechazo sí se cuenta**. Un autor que ofreció su obra inédita a una
     * persona concreta merece saber que es un no, aunque solo sea para
     * ofrecérsela a otra.
     */
    public function testDecliningTellsTheAuthorAndLeavesNoAccess(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $invitationId = $this->invite($workId, $author['token'], $lectora['userId']);
        $this->resolve($invitationId, $lectora['token'], 'DECLINED');

        self::assertSame('DECLINED', $this->payload()['status']);
        self::assertFalse($this->isBetaReader($workId, $lectora['userId']));

        $declined = $this->lastAnnouncementOf('BetaReaderInvitationDeclined');
        self::assertSame($author['userId'], $declined['authorId']);
        self::assertSame($lectora['userId'], $declined['readerId']);
    }

    /**
     * `RN-10`: rechazar no cierra la puerta. El autor puede volver a
     * ofrecérsela más adelante.
     */
    public function testAfterDecliningTheAuthorMayInviteAgain(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $this->resolve($this->invite($workId, $author['token'], $lectora['userId']), $lectora['token'], 'DECLINED');

        $this->send($workId, $author['token'], $lectora['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * **`RN-3`, la asimetría que más cuesta explicar**: invitar vale en
     * cualquier modalidad, aunque solicitar en una obra `PUBLIC` se rechace.
     * La diferencia está en quién trabaja — una solicitud le pide trabajo al
     * autor, una invitación es el autor decidiendo hacerlo.
     */
    public function testInvitingWorksInEveryAccessMode(): void
    {
        $author = $this->activatedPerson('autora');

        foreach (['PUBLIC', 'ON_REQUEST', 'PRIVATE'] as $index => $mode) {
            $lectora = $this->activatedPerson('lectora'.$index);
            $workId = $this->privateWork($author, 'Obra '.$mode);
            $this->accessMode($workId, $author['token'], $mode);

            $this->send($workId, $author['token'], $lectora['userId']);

            self::assertResponseStatusCodeSame(Response::HTTP_CREATED, $mode);
        }
    }

    /**
     * **`RN-12`, el caso más valioso y el más incómodo de explicar**: se
     * invita a un borrador, porque la primera persona a la que un autor
     * enseña algo suele verlo antes de que exista para nadie más.
     *
     * Y la consecuencia: se acepta, se es lector beta, y **no se lee nada
     * hasta que la obra se publique**. La invitación espera.
     */
    public function testYouMayInviteSomebodyToADraftAndTheyWaitUntilItIsPublished(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $borrador = $this->createWork($author['token'], 'En el cajón');
        $this->addChapter($borrador, $author['token'], words: 800);

        $this->resolve($this->invite($borrador, $author['token'], $lectora['userId']), $lectora['token'], 'ACCEPTED');

        self::assertTrue($this->isBetaReader($borrador, $lectora['userId']));

        $this->openWork($borrador, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Un borrador no existe para nadie más.');

        $this->changeStatus($borrador, $author['token'], 'PUBLISHED');

        $this->openWork($borrador, $lectora['token']);
        self::assertResponseIsSuccessful('Y en cuanto se publica, la espera termina.');
    }

    /**
     * `RN-2`: invitar a un identificador inventado crearía una invitación que
     * nadie puede aceptar. Se nombra el motivo, porque el autor eligió a esa
     * persona.
     */
    public function testInvitingSomebodyWhoDoesNotExistIsRefusedByName(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->privateWork($author);

        $this->send($workId, $author['token'], '0192f000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('USER_NOT_FOUND', $this->payload()['code']);
    }

    public function testTheAuthorCannotInviteThemselves(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->privateWork($author);

        $this->send($workId, $author['token'], $author['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('AUTHOR_CANNOT_BE_BETA_READER', $this->payload()['code']);
    }

    public function testNobodyInvitesToSomebodyElsesWork(): void
    {
        $author = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $this->send($workId, $otra['token'], $lectora['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testInvitingTwiceDoesNotCreateTwoInvitations(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $this->invite($workId, $author['token'], $lectora['userId']);
        $this->send($workId, $author['token'], $lectora['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('INVITATION_ALREADY_PENDING', $this->payload()['code']);
    }

    public function testInvitingWhoeverAlreadyHasAccessIsRefused(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $this->resolve($this->invite($workId, $author['token'], $lectora['userId']), $lectora['token'], 'ACCEPTED');

        $this->send($workId, $author['token'], $lectora['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ALREADY_A_BETA_READER', $this->payload()['code']);
    }

    /**
     * `RN-6` de `FEAT-RDG-004`: si ya lo pidió, lo que toca es aceptar su
     * solicitud. Dos objetos que significan lo mismo son dos sitios por los
     * que el acceso puede nacer.
     */
    public function testInvitingSomebodyWhoAlreadyAskedPointsAtTheirRequest(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->privateWork($author);
        $this->accessMode($workId, $author['token'], 'ON_REQUEST');

        $this->client->request('POST', \sprintf('/api/v1/works/%s/access-requests', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        $this->send($workId, $author['token'], $lectora['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('REQUEST_ALREADY_PENDING', $this->payload()['code']);
    }

    public function testOnlyTheInviteeResolvesTheInvitation(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $otra = $this->activatedPerson('otra');
        $workId = $this->privateWork($author);

        $invitationId = $this->invite($workId, $author['token'], $lectora['userId']);

        $this->resolve($invitationId, $otra['token'], 'ACCEPTED');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Ni siquiera el autor que la envió: él la retira.
        $this->resolve($invitationId, $author['token'], 'ACCEPTED');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertFalse($this->isBetaReader($workId, $lectora['userId']));
    }

    public function testAWithdrawnInvitationCannotBeAccepted(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $invitationId = $this->invite($workId, $author['token'], $lectora['userId']);

        $this->cancel($invitationId, $author['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->resolve($invitationId, $lectora['token'], 'ACCEPTED');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('INVITATION_ALREADY_RESOLVED', $this->payload()['code']);

        self::assertFalse($this->isBetaReader($workId, $lectora['userId']));
    }

    public function testNobodyWithdrawsAnInvitationTheyDidNotSend(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $invitationId = $this->invite($workId, $author['token'], $lectora['userId']);

        $this->cancel($invitationId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testResolvingTwiceIsRefused(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $invitationId = $this->invite($workId, $author['token'], $lectora['userId']);
        $this->resolve($invitationId, $lectora['token'], 'ACCEPTED');

        $this->resolve($invitationId, $lectora['token'], 'DECLINED');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('INVITATION_ALREADY_RESOLVED', $this->payload()['code']);
        self::assertTrue($this->isBetaReader($workId, $lectora['userId']), 'Y el acceso sigue.');
    }

    public function testAnUnknownDecisionIsRefusedInsteadOfGuessed(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $invitationId = $this->invite($workId, $author['token'], $lectora['userId']);

        // `REJECTED` es la palabra de las solicitudes, no la de aquí.
        $this->resolve($invitationId, $lectora['token'], 'REJECTED');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_DECISION', $this->payload()['code']);
        self::assertFalse($this->isBetaReader($workId, $lectora['userId']));
    }

    /**
     * `RN-4` de `FEAT-RDG-005`: el invitado ya había entrado por otro camino.
     * La invitación se cierra igual y **no nace un segundo acceso**.
     *
     * Llegar a ese estado cuesta, y el camino cuenta algo: no se puede
     * invitar a quien tiene una solicitud abierta (`RN-6` de `FEAT-RDG-004`)
     * ni a quien ya es lector beta, así que la única forma de tener
     * invitación pendiente **y** acceso vivo es que el acceso llegue después,
     * por la tercera puerta — el autor abre la obra y el invitado entra
     * poniéndose a corregir.
     */
    public function testAcceptingWhenYouAlreadyGotInLeavesOneAccess(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($author['token'], 'La obra privada');
        $chapterId = $this->addChapter($workId, $author['token'], words: 900);
        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');
        $this->accessMode($workId, $author['token'], 'PRIVATE');

        $invitationId = $this->invite($workId, $author['token'], $lectora['userId']);

        $this->accessMode($workId, $author['token'], 'PUBLIC');
        $this->changeStatus($workId, $author['token'], 'IN_CORRECTION');
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertTrue($this->isBetaReader($workId, $lectora['userId']));

        $this->resolve($invitationId, $lectora['token'], 'ACCEPTED');

        self::assertResponseIsSuccessful();
        self::assertSame('ACCEPTED', $this->payload()['status']);
        self::assertCount(1, $this->accessesOf($workId, $lectora['userId']), 'Uno, no dos.');
    }

    /**
     * **La razón de ser de esta lista**: quien decide si acepta no ha podido
     * ojear la obra —es privada—, así que la sinopsis y la clasificación de
     * contenido viajan con la oferta. Es el único sitio del producto donde
     * alguien se compromete a leer algo que no ha visto.
     */
    public function testMyInvitationsCarryEnoughOfTheWorkToDecide(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $this->classify($workId, $author['token'], adultsOnly: false, warnings: ['SELF_HARM']);
        $this->invite($workId, $author['token'], $lectora['userId'], 'Es dura, avisada quedas.');

        $this->myInvitations($lectora['token']);

        self::assertResponseIsSuccessful();
        $row = $this->payload()['invitations'][0];

        self::assertSame('La obra privada', $row['workTitle']);
        self::assertSame(['SELF_HARM'], $row['contentWarnings']);
        self::assertFalse($row['adultsOnly']);
        self::assertSame('Es dura, avisada quedas.', $row['message']);
        self::assertSame($author['userId'], $row['authorId']);
        self::assertSame('PENDING', $row['status']);
    }

    public function testTheAuthorSeesWhoTheyHaveInvited(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->privateWork($author);

        $this->invite($workId, $author['token'], $lectora['userId']);

        $this->workInvitations($workId, $author['token']);

        self::assertResponseIsSuccessful();
        self::assertSame($lectora['userId'], $this->payload()['invitations'][0]['readerId']);

        $this->workInvitations($workId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'La obra de otra persona, no.');
    }

    public function testTheTrayIsPagedByCursorAndFiltersByStatus(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->privateWork($author);

        $enviadas = [];

        for ($i = 0; $i < 3; ++$i) {
            $lectora = $this->activatedPerson('lectora'.$i);
            $enviadas[] = $this->invite($workId, $author['token'], $lectora['userId']);
        }

        $this->workInvitations($workId, $author['token'], ['limit' => '2']);
        self::assertCount(2, $this->payload()['invitations']);

        $vistas = array_column($this->payload()['invitations'], 'invitationId');
        $cursor = (string) $this->payload()['pageInfo']['nextCursor'];

        $this->workInvitations($workId, $author['token'], ['limit' => '2', 'cursor' => $cursor]);
        self::assertCount(1, $this->payload()['invitations']);
        $vistas[] = $this->payload()['invitations'][0]['invitationId'];

        self::assertEqualsCanonicalizing($enviadas, $vistas, 'Ni una repetida ni una perdida.');

        $this->cancel($enviadas[0], $author['token']);
        $this->workInvitations($workId, $author['token']);
        self::assertCount(2, $this->payload()['invitations'], 'Lo retirado sale de la bandeja.');

        $this->workInvitations($workId, $author['token'], ['status' => 'ALL']);
        self::assertCount(3, $this->payload()['invitations']);
    }

    public function testWithoutASessionThereIsNothing(): void
    {
        $this->client->request('GET', '/api/v1/me/beta-reader-invitations');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Una obra publicada y cerrada a todo el mundo: solo se entra por
     * invitación.
     *
     * @param array{token: string, userId: string} $author
     */
    private function privateWork(array $author, string $title = 'La obra privada'): string
    {
        $workId = $this->createWork($author['token'], $title);
        $this->addChapter($workId, $author['token'], words: 900);
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');
        $this->accessMode($workId, $author['token'], 'PRIVATE');

        return $workId;
    }

    private function invite(string $workId, string $token, string $userId, ?string $message = null): string
    {
        $this->send($workId, $token, $userId, $message);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['invitationId'];
    }

    private function send(string $workId, string $token, string $userId, ?string $message = null): void
    {
        $body = ['userId' => $userId];

        if (null !== $message) {
            $body['message'] = $message;
        }

        $this->client->request('POST', \sprintf('/api/v1/works/%s/beta-reader-invitations', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function resolve(string $invitationId, string $token, string $decision): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/beta-reader-invitations/%s/resolution', $invitationId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => $decision], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function cancel(string $invitationId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/beta-reader-invitations/%s', $invitationId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param array<string, string> $filters
     */
    private function workInvitations(string $workId, string $token, array $filters = []): void
    {
        $this->client->request(
            'GET',
            \sprintf('/api/v1/works/%s/beta-reader-invitations?%s', $workId, http_build_query($filters)),
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );
    }

    private function myInvitations(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/beta-reader-invitations', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function openWork(string $workId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param list<string> $warnings
     */
    private function classify(string $workId, string $token, bool $adultsOnly, array $warnings): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/content-rating', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['adultsOnly' => $adultsOnly, 'contentWarnings' => $warnings], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
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

    private function isBetaReader(string $workId, string $readerId): bool
    {
        /** @var BetaReaderAccessCheck $accesses */
        $accesses = self::getContainer()->get(BetaReaderAccessCheck::class);

        return $accesses->hasAccessTo($workId, $readerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accessesOf(string $workId, string $readerId): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var list<array<string, mixed>> $rows */
        $rows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT id FROM reading_ctx.beta_reader_access WHERE work_id = :work AND reader_id = :reader',
            ['work' => $workId, 'reader' => $readerId],
        );

        return $rows;
    }
}
