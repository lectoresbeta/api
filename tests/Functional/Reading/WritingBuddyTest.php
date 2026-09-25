<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Reading;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proponer, aceptar y rechazar un vínculo de writing buddy (`FEAT-RDG-008`,
 * `FEAT-RDG-009`).
 *
 * **La prueba que más importa es la que comprueba que no pasa nada**: el
 * vínculo aceptado **no concede acceso** a las obras del otro (`R-3`,
 * resuelta). Un acceso automático sería cómodo y abriría una puerta que no
 * pasa por la modalidad que cada autor eligió ni por la clasificación por
 * edad.
 */
final class WritingBuddyTest extends EconomyScenario
{
    /**
     * El camino entero: se propone, se ve pendiente en las dos listas, se
     * acepta y queda vivo.
     */
    public function testAProposalIsMadeSeenAndAccepted(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $linkId = $this->propose($ana['token'], $bruno['userId']);

        $deAna = $this->buddiesOf($ana['token']);
        self::assertSame('PROPOSED', $deAna[0]['status']);
        self::assertTrue($deAna[0]['proposedByMe'], 'Para pintar «esperando respuesta».');
        self::assertSame($bruno['userId'], $deAna[0]['other']['userId']);

        $deBruno = $this->buddiesOf($bruno['token']);
        self::assertFalse($deBruno[0]['proposedByMe'], 'Para pintar «Aceptar / Rechazar».');

        self::assertSame('ACCEPTED', $this->resolve($bruno['token'], $linkId, 'ACCEPT'));
        self::assertSame('ACCEPTED', $this->buddiesOf($ana['token'])[0]['status']);
    }

    /**
     * **`R-3`, resuelta**: el vínculo no habilita nada.
     *
     * Ser writing buddy de alguien no da acceso a sus obras: sigue haciendo
     * falta la solicitud o la invitación, que es por donde pasa la modalidad
     * que el autor eligió.
     */
    public function testALiveLinkGrantsNoAccessToAnything(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $linkId = $this->propose($ana['token'], $bruno['userId']);
        $this->resolve($bruno['token'], $linkId, 'ACCEPT');
        $this->consumeEverything();

        // Una obra de Bruno en borrador: su writing buddy no la ve.
        $workId = $this->createWork($bruno['token'], 'Lo que estoy escribiendo');
        $chapterId = $this->addChapter($workId, $bruno['token'], words: 900);

        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$ana['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Un borrador ajeno sigue sin existir.');

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$ana['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Rechazar cierra la propuesta y **no anuncia nada**: avisar de un «no»
     * convertiría una respuesta discreta en un desaire con acuse de recibo.
     */
    public function testDecliningClosesItQuietly(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $linkId = $this->propose($ana['token'], $bruno['userId']);

        self::assertSame('DECLINED', $this->resolve($bruno['token'], $linkId, 'DECLINE'));

        self::assertSame([], $this->buddiesOf($ana['token']), 'Los resueltos no se quedan a la vista.');
        self::assertSame([], $this->buddiesOf($bruno['token']));
        self::assertSame([], $this->announced('WritingBuddyLinked'), 'Y no se anuncia el rechazo.');
    }

    /**
     * Y tras un rechazo se puede volver a proponer: no hay vínculo vivo que
     * lo impida.
     */
    public function testAfterADeclineItCanBeProposedAgain(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->resolve($bruno['token'], $this->propose($ana['token'], $bruno['userId']), 'DECLINE');

        $this->post($ana['token'], $bruno['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * Uno vivo por par, y da igual en qué dirección se proponga: el par se
     * guarda ordenado.
     */
    public function testOnlyOneLiveLinkPerPair(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->propose($ana['token'], $bruno['userId']);

        $this->post($ana['token'], $bruno['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('WRITING_BUDDY_ALREADY_LIVE', $this->payload()['code']);

        $this->post($bruno['token'], $ana['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT, 'Tampoco al revés.');
    }

    /**
     * `FEAT-USR-011`: quien ha cerrado las propuestas no las recibe, y el
     * error **no dice si es por el ajuste o por un bloqueo** — distinguirlos
     * permitiría averiguar los ajustes de otro probando.
     */
    public function testWhoeverClosedProposalsDoesNotGetThem(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->putAs('/api/v1/me/reception-settings', $bruno['token'], ['writingBuddyProposals' => false]);
        $this->consumeEverything();

        $this->post($ana['token'], $bruno['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('PROPOSALS_NOT_ACCEPTED', $this->payload()['code']);
    }

    /**
     * Y un bloqueo también corta, con **el mismo error**: el bloqueo vence al
     * ajuste y no se distingue de él.
     */
    public function testABlockStopsItWithTheSameAnswer(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $ana['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$bruno['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        $this->post($ana['token'], $bruno['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('PROPOSALS_NOT_ACCEPTED', $this->payload()['code'], 'El mismo que el ajuste.');
    }

    /**
     * No hay vínculo de una persona.
     */
    public function testYouCannotBeYourOwnBuddy(): void
    {
        $ana = $this->activatedPerson('ana');

        $this->post($ana['token'], $ana['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CANNOT_BE_YOUR_OWN_BUDDY', $this->payload()['code']);
    }

    /**
     * **Solo la resuelve quien la recibió.** Para quien la propuso responde
     * `404`, igual que para alguien ajeno: quién le ha propuesto qué a quién
     * no se le debe a nadie que no sea parte.
     */
    public function testOnlyTheOneProposedToCanResolveIt(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');
        $curiosa = $this->activatedPerson('curiosa');

        $linkId = $this->propose($ana['token'], $bruno['userId']);

        foreach ([$ana['token'], $curiosa['token']] as $token) {
            $this->put($token, $linkId, 'ACCEPT');
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Y resolverla dos veces tampoco: ya no está pendiente.
     */
    public function testItCannotBeResolvedTwice(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $linkId = $this->propose($ana['token'], $bruno['userId']);
        $this->resolve($bruno['token'], $linkId, 'ACCEPT');

        $this->put($bruno['token'], $linkId, 'DECLINE');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * `FEAT-NOT-005`: la propuesta avisa a quien la recibe, y no a quien la
     * hace.
     */
    public function testTheProposalNotifiesWhoeverGetsIt(): void
    {
        $ana = $this->activatedPerson('ana');
        $bruno = $this->activatedPerson('bruno');

        $this->propose($ana['token'], $bruno['userId']);
        $this->consumeEverything();

        self::assertContains('WRITING_BUDDY_PROPOSED', $this->kindsOf($bruno['token']));
        self::assertSame([], $this->kindsOf($ana['token']), 'Quien propone ya sabe lo que ha hecho.');
    }

    public function testAnUnknownPersonIsIndistinguishableFromAnUnactivatedOne(): void
    {
        $ana = $this->activatedPerson('ana');
        $this->signedInWithoutActivating('sinactivar');

        $this->post($ana['token'], '11111111-1111-4111-8111-111111111111');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WRITING_BUDDY_LINK_NOT_FOUND', $this->payload()['code']);
    }

    public function testWithoutASessionThereIsNothingToPropose(): void
    {
        $ana = $this->activatedPerson('ana');

        $this->client->request('POST', \sprintf('/api/v1/users/%s/writing-buddy-proposals', $ana['userId']));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function propose(string $token, string $partnerId): string
    {
        $this->post($token, $partnerId);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['linkId'];
    }

    private function post(string $token, string $partnerId): void
    {
        $this->client->request('POST', \sprintf('/api/v1/users/%s/writing-buddy-proposals', $partnerId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function resolve(string $token, string $linkId, string $decision): string
    {
        $this->put($token, $linkId, $decision);

        self::assertResponseIsSuccessful();

        return (string) $this->payload()['status'];
    }

    private function put(string $token, string $linkId, string $decision): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/writing-buddy-proposals/%s/resolution', $linkId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => $decision], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buddiesOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/writing-buddies', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->payload()['writingBuddies'];

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function kindsOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/notifications', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{kind: string}> $data */
        $data = $this->payload()['data'];

        return array_map(static fn (array $row): string => $row['kind'], $data);
    }
}
