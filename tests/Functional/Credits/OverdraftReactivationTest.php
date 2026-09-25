<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use Doctrine\DBAL\Connection;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Application\Service\GrantOverdrafts;
use LectoresBeta\Credits\Overdraft\Domain\Repository\OverdraftGrantRepository;
use LectoresBeta\Credits\Overdraft\Domain\Repository\ReactivationCandidateRepository;
use LectoresBeta\Credits\Overdraft\Domain\Service\OverdraftPolicy;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El descubierto como gancho de reactivación (`FEAT-CRD-019`).
 *
 * **Esta ficha no añade mecánica.** Provoca a propósito la situación que
 * `FEAT-CRD-018` ya sabe manejar —el corrector cobra, el autor queda en
 * negativo, la corrección llega bloqueada— y le pone un presupuesto. Así que
 * lo que estas pruebas defienden no es qué pasa después, sino **que el
 * presupuesto se respeta y que no se le tiende el gancho a quien no toca**.
 *
 * Tres condiciones son obligatorias y son las que separan esto de un patrón
 * oscuro: el texto seguía abierto, la persona ha corregido antes, y no ha
 * dicho que no quiere. Cada una tiene su caso.
 */
final class OverdraftReactivationTest extends EconomyScenario
{
    /**
     * El camino entero: a un autor dormido se le abre un capítulo que no
     * puede pagar, alguien lo corrige, el corrector cobra **íntegro**, el
     * autor queda en negativo y la corrección llega bloqueada.
     *
     * `RN-3` es lo que se comprueba en medio: el corrector no se entera de
     * nada. Para él no cambia absolutamente nada.
     */
    public function testAnOverdraftOpensAChapterItsAuthorCannotPay(): void
    {
        $dormida = $this->aDormantAuthorWithAnOpenChapter();
        $lectora = $this->activatedPerson('nueva');

        self::assertFalse(
            $this->canBeCorrected($dormida['chapterId'], $lectora['token']),
            'Sin saldo no se recibe: es el estado de partida.',
        );

        self::assertCount(1, $this->grant(), 'Y ahora el cupo la elige.');
        $this->consumeEverything();

        self::assertTrue(
            $this->canBeCorrected($dormida['chapterId'], $lectora['token']),
            'RN-2: la elegibilidad abre el capítulo.',
        );

        $this->deliverCorrection($dormida['chapterId'], $lectora['token']);
        // El hecho lo publica el consumidor que cobra la corrección, no la
        // petición: hay que entregar antes de mirar.
        $this->consumeEverything();
        $anuncios = $this->announced('OverdraftCorrectionGranted');

        self::assertCount(1, $anuncios, 'El hecho económico sale, y es del que vive el correo.');
        self::assertSame($dormida['userId'], $anuncios[0]['authorId']);
        self::assertGreaterThan(0, $anuncios[0]['creditsNeeded'], 'RN-4: una meta concreta, no «repón saldo».');

        self::assertLessThan(0, (int) $this->balanceOf($dormida['userId']), 'La autora queda en negativo.');
        self::assertGreaterThan(10, (int) $this->balanceOf($lectora['userId']), 'RN-3: la correctora cobra íntegro.');
    }

    /**
     * `RN-2` y `RN-2b`: **en un periodo nunca se conceden más que el cupo**,
     * y lo que no se usa **no se acumula**.
     *
     * Es la decisión que hace controlable el mecanismo: el techo de emisión
     * se sabe de antemano en vez de depender de cuánta gente cruce un umbral.
     */
    public function testThePeriodQuotaIsNeverExceededAndDoesNotAccumulate(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->aDormantAuthorWithAnOpenChapter('dormida'.$i);
        }

        self::assertCount(OverdraftPolicy::DEFAULT_QUOTA, $this->grant(), 'Tres, que es el cupo.');
        self::assertSame([], $this->grant(), 'Y el cupo de la semana ya está gastado.');
    }

    /**
     * `RN-7`: **poner el cupo a cero apaga el mecanismo**, y sin efectos
     * colaterales: no se concede nada, así que no hay elegibilidad que
     * caduque, ni deuda que aparezca, ni correo que salga.
     */
    public function testAQuotaOfZeroTurnsTheWholeThingOff(): void
    {
        $dormida = $this->aDormantAuthorWithAnOpenChapter();
        $lectora = $this->activatedPerson('nueva');

        self::assertSame([], $this->grant(quota: 0));

        self::assertFalse(
            $this->canBeCorrected($dormida['chapterId'], $lectora['token']),
            'Todo sigue exactamente como estaba.',
        );
    }

    /**
     * `RN-2d` y `RN-8`: quien apaga el aviso **no se selecciona**.
     *
     * Y no es una cortesía: sin correo, un descubierto no es un gancho sino
     * deuda a espaldas de alguien. Es el peor resultado posible del
     * mecanismo, y por eso la renuncia viaja hasta `Credits` en vez de
     * comprobarse al mandar el correo.
     */
    public function testWhoeverDeclinedTheOfferIsNeverSelected(): void
    {
        $dormida = $this->aDormantAuthorWithAnOpenChapter();

        $this->client->request('PUT', '/api/v1/me/notification-preferences', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$dormida['token'],
        ], content: json_encode([
            'preferences' => [['topic' => 'REACTIVATION_OFFER', 'channel' => 'EMAIL', 'enabled' => false]],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertCount(1, $this->announced('ReactivationOfferChoiceChanged'), 'El hecho sale de `User`.');
        self::assertSame([], $this->grant(), 'Ha dicho que no, y no hay más que hablar.');
    }

    /**
     * `RN-1` y `RN-6b`: **no se presta dos veces**. Si no volvió con uno, no
     * volverá con dos, y cada intento es una hora de trabajo de un lector que
     * quizá nadie lea.
     */
    public function testNobodyGetsASecondOverdraft(): void
    {
        $this->aDormantAuthorWithAnOpenChapter();

        self::assertCount(1, $this->grant());
        self::assertSame([], $this->grant(), 'Aunque quede cupo en la semana siguiente.');
    }

    /**
     * Una de las tres condiciones obligatorias: **ha corregido antes**. Se
     * extiende crédito a quien ha demostrado que sabe devolverlo.
     */
    public function testSomebodyWhoNeverCorrectedIsNotACandidate(): void
    {
        $this->aDormantAuthorWithAnOpenChapter('novata', hasCorrectedBefore: false);

        self::assertSame([], $this->grant());
    }

    /**
     * `RN-2c`: la ventana es de 30 a 180 días. Por debajo no está dormido, y
     * por encima la probabilidad de volver cae tanto que el cupo rinde más en
     * gente más reciente.
     */
    public function testTheIdleWindowHasBothEnds(): void
    {
        $this->aDormantAuthorWithAnOpenChapter('reciente', idleDays: 5);
        $this->aDormantAuthorWithAnOpenChapter('perdida', idleDays: 400);

        self::assertSame([], $this->grant(), 'Ni quien acaba de irse ni quien no vuelve.');
    }

    /**
     * `RN-5` y la tasa de recuperación: al volver a saldo ≥ 0, la corrección
     * se desbloquea **sin intervención**, y el descubierto queda saldado.
     *
     * Esa proporción es la única cifra que dice si esto reactiva gente o
     * regala créditos (`FEAT-CRD-012`), y es la que decide si sigue
     * encendido.
     */
    public function testComingBackSettlesTheOverdraftAndUnlocksTheCorrection(): void
    {
        $dormida = $this->aDormantAuthorWithAnOpenChapter();
        $lectora = $this->activatedPerson('nueva');

        $this->grant();
        $this->consumeEverything();
        $this->deliverCorrection($dormida['chapterId'], $lectora['token']);
        $this->consumeEverything();

        self::assertSame(['granted' => 1, 'settled' => 0], $this->recovery());

        // Y ahora repone, que es lo que el mecanismo entero pretende: para
        // leer una corrección, haz una corrección. Aquí se resume con un
        // ajuste, porque lo que esta prueba defiende es el desbloqueo.
        $this->topUp($dormida['userId'], 40);
        $this->consumeEverything();

        self::assertSame(['granted' => 1, 'settled' => 1], $this->recovery());
    }

    /**
     * @return array{token: string, userId: string, chapterId: string}
     */
    private function aDormantAuthorWithAnOpenChapter(
        string $local = 'dormida',
        bool $hasCorrectedBefore = true,
        int $idleDays = 60,
    ): array {
        $autora = $this->activatedPerson($local);
        $workId = $this->createWork($autora['token'], 'La obra de '.$local);
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        if ($hasCorrectedBefore) {
            $ajena = $this->aChapterOpenForCorrection($local.'-ajena');
            $this->deliverCorrection($ajena, $autora['token']);
            $this->consumeEverything();
        }

        // Sin saldo, que es el estado de quien no puede pagar una corrección.
        $this->topUp($autora['userId'], -(int) $this->balanceOf($autora['userId']));
        $this->consumeEverything();

        // Y dormido. El paso del tiempo es lo único que una prueba por HTTP
        // no puede producir, así que se escribe.
        $this->backdate($autora['userId'], $idleDays);

        return [...$autora, 'chapterId' => $chapterId];
    }

    private function aChapterOpenForCorrection(string $local): string
    {
        $otra = $this->activatedPerson($local);
        $workId = $this->createWork($otra['token'], 'La obra de '.$local);
        $chapterId = $this->addChapter($workId, $otra['token'], words: 300);
        $this->saveQuestionnaire($workId, $otra['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $otra['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $otra['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $otra['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        return $chapterId;
    }

    private function canBeCorrected(string $chapterId, string $token): bool
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        $started = Response::HTTP_CREATED === $this->client->getResponse()->getStatusCode();
        $this->capture();

        if ($started) {
            // Se deshace: preguntar no debe dejar rastro, y una corrección
            // abierta cuenta para el tope de simultáneas.
            $this->client->request('DELETE', \sprintf('/api/v1/corrections/%s', $this->payload()['correctionId']), server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ]);
            $this->capture();
            $this->consumeEverything();
        }

        return $started;
    }

    private function deliverCorrection(string $chapterId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
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
        $this->capture();
    }

    /**
     * @return list<\LectoresBeta\Credits\Overdraft\Domain\ValueObject\ReactivationCandidate>
     */
    private function grant(?int $quota = null): array
    {
        $container = self::getContainer();

        if (null === $quota) {
            /** @var GrantOverdrafts $grant */
            $grant = $container->get(GrantOverdrafts::class);

            return $grant();
        }

        // El cupo es un argumento del servicio, así que apagarlo es montarlo
        // con otro número. Las demás piezas son las de verdad.
        /** @var ReactivationCandidateRepository $candidates */
        $candidates = $container->get(ReactivationCandidateRepository::class);
        /** @var OverdraftGrantRepository $grants */
        $grants = $container->get(OverdraftGrantRepository::class);
        /** @var RefreshCorrectability $correctability */
        $correctability = $container->get(RefreshCorrectability::class);
        /** @var TransactionalSession $session */
        $session = $container->get(TransactionalSession::class);
        /** @var Clock $clock */
        $clock = $container->get(Clock::class);

        $grant = new GrantOverdrafts($candidates, $grants, $correctability, $session, $clock, $quota);

        return $grant();
    }

    /**
     * @return array{granted: int, settled: int}
     */
    private function recovery(): array
    {
        /** @var OverdraftGrantRepository $grants */
        $grants = self::getContainer()->get(OverdraftGrantRepository::class);

        return $grants->recovery();
    }

    private function topUp(string $userId, int $amount): void
    {
        if (0 === $amount) {
            return;
        }

        $admin = $this->administrator('tesoreria');

        $this->client->request('POST', \sprintf('/api/v1/admin/users/%s/credit-adjustment', $userId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$admin['token'],
        ], content: json_encode([
            'amount' => $amount,
            'reason' => 'Ajuste de la prueba.',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    /**
     * Envejecer los movimientos de alguien. El paso del tiempo es lo único
     * que una prueba por HTTP no puede provocar.
     */
    private function backdate(string $userId, int $days): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);

        $connection->executeStatement(
            'UPDATE credits_ctx.credit_transaction SET occurred_at = occurred_at - :interval::interval WHERE user_id = :user',
            ['interval' => \sprintf('%d days', $days), 'user' => $userId],
        );

        // Y el saldo no se toca: envejecer no es gastar.
        self::assertSame(0, (int) $this->balanceOf(UserId::fromString($userId)->value()));
    }
}
