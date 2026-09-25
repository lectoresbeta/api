<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRestrictionRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Presentar una reclamación (`FEAT-MOD-001`) y seguirla (`FEAT-MOD-010`).
 *
 * **El botón de reclamar es también una forma de no pagar**: reclamar una
 * corrección devuelve créditos si se estima, así que sin defensas se aprende
 * enseguida a reclamarlo todo. Por eso casi todo lo que se prueba aquí es
 * quién **no** puede reclamar, y cuándo.
 */
final class SubmitClaimTest extends EconomyScenario
{
    public function testAnyoneCanClaimAboutAWorkAndFollowItAfterwards(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->publishedWork($autora);

        $this->claim($lectora['token'], 'WORK', $workId, 'OFFENSIVE', 'Hay una escena muy explícita sin aviso.');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertFalse($this->payload()['alreadyExisted']);
        self::assertSame('PENDING', $this->payload()['status']);

        $this->myClaims($lectora['token']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->payload()['claims']);
        self::assertSame($workId, $this->payload()['claims'][0]['targetId']);
    }

    /**
     * `RN-2`: reclamar dos veces suele ser un doble clic, y responder con un
     * error a eso sería castigar la torpeza. Diez denuncias de una persona
     * sobre el mismo texto no son diez señales.
     */
    public function testClaimingTwiceReturnsTheSameClaim(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->publishedWork($autora);

        $this->claim($lectora['token'], 'WORK', $workId, 'SPAM', null);
        $primera = $this->payload()['claimId'];

        $this->claim($lectora['token'], 'WORK', $workId, 'OFFENSIVE', 'Otra cosa.');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame($primera, $this->payload()['claimId']);
        self::assertTrue($this->payload()['alreadyExisted']);

        $this->myClaims($lectora['token']);
        self::assertCount(1, $this->payload()['claims'], 'Una sola, no dos.');
    }

    /**
     * `RN-6`: sin tope, el botón de reclamar es una forma barata de no pagar
     * ninguna corrección.
     */
    public function testThereIsAMonthlyLimit(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        for ($numero = 0; $numero < 3; ++$numero) {
            $this->claim($lectora['token'], 'WORK', $this->publishedWork($autora, \sprintf('Obra %d', $numero)), 'SPAM', null);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        $this->claim($lectora['token'], 'WORK', $this->publishedWork($autora, 'Una más'), 'SPAM', null);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('CLAIM_LIMIT_REACHED', $this->payload()['code']);
    }

    /**
     * `RN-6b`: el bloqueo por reclamaciones desestimadas es acumulativo, y
     * lleva fecha. «No puedes reclamar» sin fecha no le dice nada a quien
     * tenía algo legítimo que denunciar.
     */
    public function testADismissedClaimBlocksTheButtonAndSaysUntilWhen(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->publishedWork($autora);

        $this->dismissAClaimOf($lectora['userId']);

        $this->claim($lectora['token'], 'WORK', $workId, 'SPAM', null);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('CLAIM_BLOCKED', $this->payload()['code']);
        self::assertNotEmpty($this->payload()['blockedUntil'], 'Y dice hasta cuándo.');
    }

    /**
     * `RN-4`: una corrección la reclama el autor de la obra corregida y nadie
     * más. Una ajena responde lo mismo que una inexistente.
     */
    public function testOnlyTheAuthorOfTheCorrectedWorkCanClaimAboutACorrection(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $extrana = $this->activatedPerson('extrana');

        $this->claim($extrana['token'], 'CORRECTION', $correctionId, 'NO_VALUE', null);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->claim($lectora['token'], 'CORRECTION', $correctionId, 'NO_VALUE', null);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Ni siquiera quien la escribió.');

        $this->claim($autora['token'], 'CORRECTION', $correctionId, 'NO_VALUE', 'No responde a nada de lo que pregunté.');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `RN-3`: el motivo sale de un catálogo. Uno inventado se rechaza en vez
     * de guardarse, porque el catálogo es lo que ordena el trabajo del
     * moderador.
     */
    public function testTheReasonComesFromTheCatalogue(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->publishedWork($autora);

        $this->claim($lectora['token'], 'WORK', $workId, 'NO_ME_GUSTA', null);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CLAIM_REASON_UNKNOWN', $this->payload()['code']);
    }

    /**
     * `RN-1`: la reclamación no produce ningún efecto. No oculta la obra, no
     * la congela y no avisa al reclamado.
     */
    public function testClaimingChangesNothingAboutTheTarget(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->publishedWork($autora);

        $this->claim($lectora['token'], 'WORK', $workId, 'OFFENSIVE', null);

        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful('La obra sigue ahí, sin ocultar ni congelar.');
        self::assertSame('PUBLISHED', $this->payload()['status']);

        $this->myClaims($autora['token']);
        self::assertSame([], $this->payload()['claims'], 'Y quien la escribió no se entera.');
    }

    public function testWithoutASessionThereIsNothing(): void
    {
        $this->client->request('POST', '/api/v1/claims', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('GET', '/api/v1/me/claims');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string}
     */
    private function aDeliveredCorrection(): array
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La obra corregida');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: json_encode([
            'answers' => array_map(
                static fn (array $question): array => [
                    'questionId' => $question['questionId'],
                    'text' => implode(' ', array_fill(0, 60, 'palabra')),
                ],
                $questions,
            ),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $correctionId = (string) $this->payload()['correctionId'];
        $this->capture();
        $this->consumeEverything();

        return [$autora, $lectora, $correctionId];
    }

    /**
     * @param array{token: string, userId: string} $autora
     */
    private function publishedWork(array $autora, string $title = 'La obra reclamada'): string
    {
        $workId = $this->createWork($autora['token'], $title);
        $this->addChapter($workId, $autora['token'], words: 900);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        return $workId;
    }

    private function dismissAClaimOf(string $userId): void
    {
        /** @var ClaimRestrictionRepository $restrictions */
        $restrictions = self::getContainer()->get(ClaimRestrictionRepository::class);

        $restriction = new \LectoresBeta\Moderation\Claim\Domain\Entity\ClaimRestriction(
            PartyId::fromString($userId),
            new \DateTimeImmutable(),
        );
        $restriction->claimDismissed(new \DateTimeImmutable());
        $restrictions->save($restriction);

        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();
    }

    private function claim(string $token, string $targetType, string $targetId, string $reason, ?string $description): void
    {
        $body = ['targetType' => $targetType, 'targetId' => $targetId, 'reason' => $reason];

        if (null !== $description) {
            $body['description'] = $description;
        }

        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function myClaims(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/claims', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param array<string, mixed> $body
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
