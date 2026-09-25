<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La salud de la economía (`FEAT-CRD-012`).
 *
 * **No es un panel bonito: es el instrumento que dice cuál de las palancas
 * hay que mover.** Un sistema de créditos sin medición se descubre roto por
 * las quejas, y las dos formas de morir —nadie tiene créditos, o los créditos
 * no valen nada— tardan semanas en manifestarse y son caras de revertir,
 * porque para entonces los saldos ya están formados.
 *
 * La prueba de la invariante en sí es unitaria: ahí se pueden fabricar los
 * estados rotos que la aplicación no sabe producir. Aquí se comprueba lo
 * contrario —que **la aplicación de verdad no los produce**— y que el panel
 * está cerrado a quien no debe verlo.
 */
final class EconomyHealthTest extends EconomyScenario
{
    /**
     * El recorrido económico completo —bienvenida, cobro, abono, propina—
     * deja la invariante en pie. Es la comprobación que ningún test de un
     * caso de uso concreto hace.
     */
    public function testTheWholeEconomyKeepsTheInvariant(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        $this->client->request('POST', \sprintf('/api/v1/corrections/%s/tip', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['amount' => 2], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        $health = $this->health($this->administrator('jefa')['token']);

        self::assertTrue($health['invariant']['holds'], 'Ningún caso de uso ha creado créditos de la nada.');
        self::assertNull($health['invariant']['failure']);
        self::assertSame(
            $health['invariant']['issued'],
            $health['invariant']['balances'],
            'Lo que hay repartido es exactamente lo que los grifos emitieron.',
        );
        self::assertNotContains('ACCOUNTING_INVARIANT_BROKEN', $health['alerts']);
    }

    /**
     * Las cifras del reparto salen de los saldos, no de contadores
     * paralelos (`RN-1`).
     */
    public function testTheSpreadCountsTheAccountsThatExist(): void
    {
        $this->activatedPerson('primera');
        $this->activatedPerson('segunda');
        $admin = $this->administrator('jefa');
        $this->consumeEverything();

        $health = $this->health($admin['token']);

        self::assertSame(3, $health['accounts']['total']);
        self::assertSame(0, $health['accounts']['inDebt'], 'Nadie ha corregido todavía.');
        self::assertSame(0, $health['accounts']['deepestDebt']);
        self::assertEqualsWithDelta(0.0, $health['accounts']['shareAtZeroOrBelow'], 0.0001);
    }

    /**
     * `RN-2`: son cifras agregadas. **El saldo más bajo del sistema es un
     * número, no una persona.**.
     */
    public function testDebtShowsUpAsAFigureAndNotAsAName(): void
    {
        [$autora] = $this->anAuthorInTheRed();
        $admin = $this->administrator('jefa');

        $health = $this->health($admin['token']);

        self::assertLessThan(0, $health['accounts']['deepestDebt']);
        self::assertSame(1, $health['accounts']['inDebt']);
        self::assertTrue($health['invariant']['holds'], 'Una deuda es un saldo negativo, no un crédito perdido.');

        $encoded = json_encode($health, \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($autora['userId'], $encoded, 'Ni un identificador de usuario.');
    }

    /**
     * Un ajuste manual es la única vía de crédito que no es transferencia ni
     * regla automática, así que se cuenta aparte: si hacen falta muchos, algo
     * de más arriba no está funcionando.
     */
    public function testManualAdjustmentsAreCountedApart(): void
    {
        $admin = $this->administrator('jefa');

        $health = $this->health($admin['token']);

        self::assertSame(0, $health['manualAdjustments']['count']);
        self::assertSame(0, $health['manualAdjustments']['net']);
    }

    /**
     * La tasa de recuperación es **nula** mientras no se haya concedido
     * ningún descubierto. Un cero diría que ninguno se recupera, que es una
     * afirmación sobre datos que no existen.
     */
    public function testTheRecoveryRateIsSilentUntilThereIsSomethingToMeasure(): void
    {
        $health = $this->health($this->administrator('jefa')['token']);

        self::assertSame(0, $health['overdraft']['granted']);
        self::assertNull($health['overdraft']['recoveryRate']);
        self::assertNotContains('OVERDRAFT_NOT_RECOVERED', $health['alerts']);
    }

    /**
     * El panel es del administrador. Ni un usuario normal ni nadie sin sesión
     * ven cómo está repartida la economía.
     */
    public function testItIsClosedToEverybodyElse(): void
    {
        $cualquiera = $this->activatedPerson('cualquiera');

        $this->client->request('GET', '/api/v1/admin/credits/health');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('GET', '/api/v1/admin/credits/health', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$cualquiera['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Una foto de ahora mismo. Cacheada deja de serlo, y quien la lee está
     * mirando justamente si algo acaba de cambiar.
     */
    public function testItIsNeverCached(): void
    {
        $this->health($this->administrator('jefa')['token']);

        self::assertStringContainsString(
            'no-store',
            (string) $this->client->getResponse()->headers->get('Cache-Control'),
        );
    }

    /**
     * Una autora en rojo, por el camino real: dos lectoras dentro del mismo
     * capítulo a la vez (`FEAT-CRD-018`).
     *
     * @return array{0: array{token: string, userId: string}}
     */
    private function anAuthorInTheRed(): array
    {
        $autora = $this->activatedPerson('autora');
        $primera = $this->activatedPerson('primera');
        $segunda = $this->activatedPerson('segunda');

        $workId = $this->createWork($autora['token'], 'La obra que endeuda');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 6000);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        foreach ([$primera, $segunda] as $lectora) {
            $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            $this->capture();
        }

        $this->consumeEverything();

        foreach ([$primera, $segunda] as $lectora) {
            $this->submitAs($chapterId, $lectora['token']);
        }

        $this->consumeEverything();

        return [$autora];
    }

    private function submitAs(string $chapterId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

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
     * @return array<string, mixed>
     */
    private function health(string $token): array
    {
        $this->client->request('GET', '/api/v1/admin/credits/health', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }
}
