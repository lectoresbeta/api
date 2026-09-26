<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Reading;

use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\Reading\BetaReaderAccess\Application\Contract\BetaReaderAccessCheck;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El camino `ON_REQUEST`: pedir y que te contesten (`FEAT-RDG-002`,
 * `FEAT-RDG-003`).
 *
 * Es el camino que desbloquea la modalidad por defecto del producto: una obra
 * nace `ON_REQUEST`, así que hasta ahora **publicar una obra no la abría a
 * nadie**.
 *
 * Lo que se defiende aquí, por encima de los códigos de error, son dos cosas:
 * que una solicitud no concede nada por sí sola, y que aceptar **concede el
 * acceso en el acto** — sin cola de por medio, porque los dos agregados son
 * del mismo contexto.
 */
final class AccessRequestTest extends EconomyScenario
{
    public function testAskingAndBeingAcceptedMakesYouABetaReader(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        self::assertFalse($this->isBetaReader($workId, $lectora['userId']));

        $requestId = $this->ask($workId, $lectora['token'], '¿Me dejas leerla?');
        self::assertFalse($this->isBetaReader($workId, $lectora['userId']), 'Pedir no concede nada.');
        self::assertSame($author['userId'], $this->lastAnnouncementOf('AccessRequested')['authorId']);

        $this->resolve($requestId, $author['token'], 'ACCEPTED');

        self::assertResponseIsSuccessful();
        self::assertSame('ACCEPTED', $this->payload()['status']);

        // **Sin consumir ninguna cola**: los dos agregados son de `Reading`,
        // así que el acceso existe en cuanto responde el endpoint.
        self::assertTrue($this->isBetaReader($workId, $lectora['userId']));
    }

    /**
     * La consecuencia que hace que todo esto sirva de algo: el acceso abre la
     * quinta puerta de `WorkReadPolicy`, así que el lector lee una obra que
     * antes no podía abrir.
     */
    public function testOnceAcceptedTheReaderCanOpenTheWork(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $this->openWork($workId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Sin acceso no existe.');

        $this->resolve($this->ask($workId, $lectora['token']), $author['token'], 'ACCEPTED');

        $this->openWork($workId, $lectora['token']);
        self::assertResponseIsSuccessful();
    }

    public function testBeingRejectedLeavesNoAccessAndTheReaderIsTold(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $requestId = $this->ask($workId, $lectora['token']);
        $this->resolve($requestId, $author['token'], 'REJECTED');

        self::assertSame('REJECTED', $this->payload()['status']);
        self::assertFalse($this->isBetaReader($workId, $lectora['userId']));

        self::assertSame($lectora['userId'], $this->lastAnnouncementOf('AccessRequestRejected')['readerId']);
    }

    /**
     * `RN-9`: tras un rechazo se puede volver a pedir. Cerrar la puerta
     * convertiría un «ahora no» en un «nunca».
     */
    public function testAfterARejectionYouMayAskAgain(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $this->resolve($this->ask($workId, $lectora['token']), $author['token'], 'REJECTED');

        $this->request($workId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `RN-3`: una solicitud pendiente por par. Lo sostiene un índice único
     * parcial, no esta lectura previa.
     */
    public function testAskingTwiceDoesNotCreateTwoRequests(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $this->ask($workId, $lectora['token']);
        $this->request($workId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('REQUEST_ALREADY_PENDING', $this->payload()['code']);
    }

    /**
     * `RN-2`, y la asimetría que más cuesta explicar: `PUBLIC` rechaza la
     * solicitud **por el motivo contrario** a `PRIVATE`. Ahí no hace falta
     * pedir nada, y aceptarla le daría trabajo al autor para nada.
     */
    public function testAWorkThatIsNotOnRequestRefusesTheRequest(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        foreach (['PUBLIC', 'PRIVATE'] as $mode) {
            $workId = $this->onRequestWork($author);
            $this->accessMode($workId, $author['token'], $mode);

            $this->request($workId, $lectora['token']);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, $mode);
            self::assertSame('WORK_DOES_NOT_TAKE_REQUESTS', $this->payload()['code']);
        }
    }

    /**
     * `RN-1`: el borrador de otra persona responde lo mismo que una obra
     * inexistente. Distinguirlos sería decirle a un desconocido que ahí hay
     * algo escrito.
     */
    public function testAStrangersDraftAnswersLikeAWorkThatDoesNotExist(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $borrador = $this->createWork($author['token'], 'En el cajón');
        $this->addChapter($borrador, $author['token'], words: 600);

        $this->request($borrador, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);

        $this->request('0192f000-0000-7000-8000-000000000000', $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code'], 'La misma respuesta.');
    }

    /**
     * `RN-1`, la otra mitad: una obra para adultos no se distingue de una
     * inexistente ante quien no ha declarado su edad. Conceder el acceso y
     * que después no pudiera leerla sería peor — le habría hecho pedirlo
     * para nada.
     */
    public function testAnAdultsOnlyWorkIsInvisibleToWhoeverHasNotSaidTheirAge(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $this->classify($workId, $author['token'], adultsOnly: true);

        $this->request($workId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * `RN-5`, y aquí **sí** se nombra el motivo: el autor está mirando su
     * propia obra, y fingir que no existe le confundiría sobre algo suyo.
     */
    public function testTheAuthorCannotAskToReadTheirOwnWork(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->onRequestWork($author);

        $this->request($workId, $author['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('AUTHOR_CANNOT_BE_BETA_READER', $this->payload()['code']);
    }

    public function testWhoeverAlreadyHasAccessDoesNotAsk(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $this->resolve($this->ask($workId, $lectora['token']), $author['token'], 'ACCEPTED');

        $this->request($workId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('ALREADY_A_BETA_READER', $this->payload()['code']);
    }

    public function testNobodyResolvesTheRequestsOfSomebodyElsesWork(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $otra = $this->activatedPerson('otra');
        $workId = $this->onRequestWork($author);

        $requestId = $this->ask($workId, $lectora['token']);

        $this->resolve($requestId, $otra['token'], 'ACCEPTED');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Ni siquiera quien la escribió la resuelve: contestar es del autor.
        $this->resolve($requestId, $lectora['token'], 'ACCEPTED');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertFalse($this->isBetaReader($workId, $lectora['userId']));
    }

    public function testResolvingTwiceIsRefused(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $requestId = $this->ask($workId, $lectora['token']);
        $this->resolve($requestId, $author['token'], 'ACCEPTED');

        $this->resolve($requestId, $author['token'], 'REJECTED');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('REQUEST_ALREADY_RESOLVED', $this->payload()['code']);
        self::assertTrue($this->isBetaReader($workId, $lectora['userId']), 'Y el acceso sigue.');
    }

    /**
     * Una decisión que no existe **no se interpreta como un rechazo**.
     * Adivinar en una operación que concede acceso a obra inédita es
     * exactamente donde no conviene adivinar.
     */
    public function testAnUnknownDecisionIsRefusedInsteadOfGuessed(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $requestId = $this->ask($workId, $lectora['token']);
        $this->resolve($requestId, $author['token'], 'PUES_NO');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_DECISION', $this->payload()['code']);

        $this->myRequests($lectora['token']);
        self::assertSame('PENDING', $this->payload()['requests'][0]['status'], 'Sigue sin resolver.');
    }

    /**
     * `RN-4` de `FEAT-RDG-003`, el caso que más cuesta ver y el único donde
     * los dos caminos de acceso se cruzan: la solicitud sigue esperando y
     * entretanto el autor abre la obra, así que el lector entra **por la
     * puerta de al lado** poniéndose a corregir.
     *
     * Aceptar la solicitud vieja la cierra igual y **no crea un segundo
     * acceso**: la invariante de uno vivo por par manda sobre la operación.
     */
    public function testAcceptingSomebodyWhoAlreadyGotInLeavesOneAccess(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($author['token'], 'La obra a solicitud');
        $chapterId = $this->addChapter($workId, $author['token'], words: 900);
        $this->saveQuestionnaire($workId, $author['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');

        $requestId = $this->ask($workId, $lectora['token']);

        // El autor abre la obra y el lector entra corrigiendo.
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

        $this->resolve($requestId, $author['token'], 'ACCEPTED');

        self::assertResponseIsSuccessful();
        self::assertSame('ACCEPTED', $this->payload()['status']);
        self::assertCount(1, $this->accessesOf($workId, $lectora['userId']), 'Uno, no dos.');
    }

    /**
     * `RN-9` de `FEAT-RDG-003`: la modalidad no se comprueba al resolver. El
     * autor pasó la obra a `PRIVATE` mientras la solicitud esperaba, y
     * aceptar sigue siendo lo correcto — la modalidad gobierna quién puede
     * entrar a partir de ahora.
     */
    public function testTheModeIsNotCheckedWhenResolving(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $requestId = $this->ask($workId, $lectora['token']);
        $this->accessMode($workId, $author['token'], 'PRIVATE');

        $this->resolve($requestId, $author['token'], 'ACCEPTED');

        self::assertResponseIsSuccessful();
        self::assertTrue($this->isBetaReader($workId, $lectora['userId']));

        // Y lo que hace que sirva de algo: lee una obra `PRIVATE`.
        $this->openWork($workId, $lectora['token']);
        self::assertResponseIsSuccessful();
    }

    /**
     * `RN-7`: cancelar no avisa a nadie, y deja la vía libre para volver a
     * pedir.
     */
    public function testTheReaderWithdrawsTheirOwnRequest(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $requestId = $this->ask($workId, $lectora['token']);

        $this->cancel($requestId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->workRequests($workId, $author['token']);
        self::assertSame([], $this->payload()['requests'], 'Desaparece de la bandeja del autor.');

        $this->request($workId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'Y se puede volver a pedir.');
    }

    public function testNobodyWithdrawsSomebodyElsesRequest(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $requestId = $this->ask($workId, $lectora['token']);

        $this->cancel($requestId, $author['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * La bandeja del autor lleva **el mensaje**, que es lo único que
     * convierte una lista de identificadores en una decisión.
     */
    public function testTheAuthorsTrayCarriesWhoAsksAndWhatTheyWrote(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $this->ask($workId, $lectora['token'], 'Me encantó tu anterior relato.');

        $this->workRequests($workId, $author['token']);

        self::assertResponseIsSuccessful();
        $row = $this->payload()['requests'][0];

        self::assertSame($lectora['userId'], $row['readerId']);
        self::assertSame('Me encantó tu anterior relato.', $row['message']);
        self::assertSame('PENDING', $row['status']);
        self::assertSame('La obra a solicitud', $row['workTitle']);
        self::assertNull($this->payload()['pageInfo']['nextCursor']);
    }

    public function testTheTrayOfSomebodyElsesWorkIsNotThere(): void
    {
        $author = $this->activatedPerson('autora');
        $otra = $this->activatedPerson('otra');
        $workId = $this->onRequestWork($author);

        $this->workRequests($workId, $otra['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * La paginación por cursor, con la propiedad que la justifica: ninguna
     * fila se repite ni se pierde entre páginas.
     */
    public function testTheTrayIsPagedByCursor(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->onRequestWork($author);

        $esperadas = [];

        for ($i = 0; $i < 3; ++$i) {
            $lectora = $this->activatedPerson('lectora'.$i);
            $esperadas[] = $this->ask($workId, $lectora['token']);
        }

        $this->workRequests($workId, $author['token'], ['limit' => '2']);
        self::assertCount(2, $this->payload()['requests']);
        self::assertTrue($this->payload()['pageInfo']['hasNextPage']);

        $vistas = array_column($this->payload()['requests'], 'requestId');
        $cursor = (string) $this->payload()['pageInfo']['nextCursor'];

        $this->workRequests($workId, $author['token'], ['limit' => '2', 'cursor' => $cursor]);
        self::assertCount(1, $this->payload()['requests']);
        self::assertFalse($this->payload()['pageInfo']['hasNextPage']);

        $vistas[] = $this->payload()['requests'][0]['requestId'];

        self::assertCount(3, array_unique($vistas), 'Ninguna fila repetida.');
        self::assertEqualsCanonicalizing($esperadas, $vistas, 'Ni ninguna perdida.');
    }

    public function testACursorThisApiDidNotIssueIsRefused(): void
    {
        $author = $this->activatedPerson('autora');
        $workId = $this->onRequestWork($author);

        $this->workRequests($workId, $author['token'], ['cursor' => 'lo-que-sea']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_CURSOR', $this->payload()['code']);
    }

    /**
     * Por defecto la bandeja enseña **lo pendiente**, que es lo que alguien
     * viene a mirar. El histórico está a un filtro de distancia.
     */
    public function testTheTrayShowsWhatIsPendingUnlessAskedOtherwise(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $this->resolve($this->ask($workId, $lectora['token']), $author['token'], 'REJECTED');

        $this->workRequests($workId, $author['token']);
        self::assertSame([], $this->payload()['requests']);

        $this->workRequests($workId, $author['token'], ['status' => 'ALL']);
        self::assertCount(1, $this->payload()['requests']);

        $this->workRequests($workId, $author['token'], ['status' => 'LO_QUE_SEA']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_FILTER_VALUE', $this->payload()['code']);
    }

    public function testMyRequestsCarryTheTitleOfEachWork(): void
    {
        $author = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($author);

        $this->ask($workId, $lectora['token']);
        $this->myRequests($lectora['token']);

        self::assertResponseIsSuccessful();
        self::assertSame($workId, $this->payload()['requests'][0]['workId']);
        self::assertSame('La obra a solicitud', $this->payload()['requests'][0]['workTitle']);
    }

    public function testWithoutASessionThereIsNothing(): void
    {
        $this->client->request('GET', '/api/v1/me/access-requests');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Una obra publicada y abierta a solicitud, que es la modalidad con la
     * que nace toda obra.
     *
     * @param array{token: string, userId: string} $author
     */
    private function onRequestWork(array $author): string
    {
        $workId = $this->createWork($author['token'], 'La obra a solicitud');
        $this->addChapter($workId, $author['token'], words: 900);
        $this->changeStatus($workId, $author['token'], 'PUBLISHED');

        return $workId;
    }

    private function ask(string $workId, string $token, ?string $message = null): string
    {
        $this->request($workId, $token, $message);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['requestId'];
    }

    private function request(string $workId, string $token, ?string $message = null): void
    {
        $this->client->request('POST', \sprintf('/api/v1/works/%s/access-requests', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(null === $message ? [] : ['message' => $message], \JSON_THROW_ON_ERROR));
    }

    private function resolve(string $requestId, string $token, string $decision): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/access-requests/%s/resolution', $requestId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => $decision], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function cancel(string $requestId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/access-requests/%s', $requestId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param array<string, string> $filters
     */
    private function workRequests(string $workId, string $token, array $filters = []): void
    {
        $this->client->request(
            'GET',
            \sprintf('/api/v1/works/%s/access-requests?%s', $workId, http_build_query($filters)),
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );
    }

    private function myRequests(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/access-requests', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function openWork(string $workId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
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

    private function classify(string $workId, string $token, bool $adultsOnly): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/content-rating', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['adultsOnly' => $adultsOnly], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    /**
     * Toda la historia de accesos de ese par, viva o no: es lo que distingue
     * «uno vivo» de «uno en total».
     *
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

    private function isBetaReader(string $workId, string $readerId): bool
    {
        /** @var BetaReaderAccessCheck $accesses */
        $accesses = self::getContainer()->get(BetaReaderAccessCheck::class);

        return $accesses->hasAccessTo($workId, $readerId);
    }
}
