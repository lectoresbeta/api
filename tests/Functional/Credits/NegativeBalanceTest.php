<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionVisibility;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El descubierto (`FEAT-CRD-018`).
 *
 * **`RN-2` y `RN-3` juntas son el diseño entero**: se cierra la puerta de
 * recibir y se deja abierta la de dar. No hay forma de salir del descubierto
 * que no sea participar, y por eso la deuda no es un castigo sino un trabajo
 * pendiente con la comunidad.
 *
 * Todo pasa por la API de verdad y por el serializador de eventos: es la
 * única forma de comprobar que `Credits` y `Feedback` se entienden sin
 * compartir una clase.
 */
final class NegativeBalanceTest extends EconomyScenario
{
    /**
     * El recorrido entero: caer en rojo, quedarse sin poder recibir, corregir
     * para salir, y que todo se desbloquee solo.
     */
    public function testTheWayIntoDebtAndBackOut(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        $this->submit($chapterId, $primera['token']);
        $this->consumeEverything();
        self::assertGreaterThanOrEqual(0, (int) $this->balanceOf($autora['userId']), 'La primera sí estaba cubierta.');

        $correctionId = $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();

        // `RN-1`: la segunda cobra aunque la autora ya no llegue.
        self::assertLessThan(0, (int) $this->balanceOf($autora['userId']), 'La autora queda en saldo − precio, sin recargo.');
        self::assertGreaterThan(10, (int) $this->balanceOf($segunda['userId']), 'Y la lectora cobra entero.');

        // `RN-9`: lo entregado llega bloqueado. El trabajo está pagado; lo
        // que se retiene es la lectura.
        self::assertSame(CorrectionVisibility::LOCKED, $this->visibilityOf($correctionId));

        // `RN-2`: con deuda no se reciben más correcciones.
        $tercera = $this->activatedPerson('tercera');
        $this->start($chapterId, $tercera['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT, 'Su obra deja de admitir correcciones.');
        self::assertSame('CHAPTER_NOT_TAKING_CORRECTIONS', $this->payload()['code']);

        // `RN-3` y `RN-4`: corregir salda la deuda sola, sin ningún gesto de
        // pagar. Dos veces, para acabar con margen por encima de cero y poder
        // comprobar también que vuelve a recibir.
        $this->clearTheDebtByCorrecting($autora);
        $this->clearTheDebtByCorrecting($autora, 'otromecenas');

        self::assertGreaterThanOrEqual(0, (int) $this->balanceOf($autora['userId']));
        self::assertSame(CorrectionVisibility::VISIBLE, $this->visibilityOf($correctionId), 'RN-10: se desbloquea sola.');

        // Y vuelve a poder recibir.
        $this->start($chapterId, $tercera['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `RN-5`: la deuda limita lo que costó generarla y nada más. Convertirla
     * en un castigo general sería expulsar a quien más participa.
     */
    public function testDebtDoesNotBlockAnythingElse(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        $this->submit($chapterId, $primera['token']);
        $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();
        self::assertLessThan(0, (int) $this->balanceOf($autora['userId']));

        // Publicar otra obra, con deuda.
        $otra = $this->createWork($autora['token'], 'Otra obra más');
        self::assertNotEmpty($otra);

        // Y editar el perfil.
        $this->client->request('PATCH', '/api/v1/me/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['biography' => 'Sigo escribiendo.'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful('Deber créditos no es una sanción.');
    }

    /**
     * `RN-11`: lo que se ha leído, leído está. Una corrección ya visible no
     * vuelve a bloquearse porque el autor caiga en rojo más tarde.
     */
    public function testACorrectionAlreadyReadIsNeverLockedAgain(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        // La primera se paga y se lee con normalidad.
        $primeraId = $this->submit($chapterId, $primera['token']);
        $this->consumeEverything();

        self::assertGreaterThanOrEqual(0, (int) $this->balanceOf($autora['userId']));
        self::assertSame(CorrectionVisibility::VISIBLE, $this->visibilityOf($primeraId));

        // La segunda la deja en rojo.
        $segundaId = $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();

        self::assertLessThan(0, (int) $this->balanceOf($autora['userId']));
        self::assertSame(CorrectionVisibility::LOCKED, $this->visibilityOf($segundaId), 'La que la dejó en rojo, sí.');
        self::assertSame(CorrectionVisibility::VISIBLE, $this->visibilityOf($primeraId), 'La que ya había leído, no.');
    }

    /**
     * Los dos cruces se avisan. El de bajada tiene que explicar la salida; el
     * de subida existe porque el desbloqueo es automático y, por tanto,
     * invisible.
     */
    public function testBothCrossingsReachTheAuthorsInbox(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        $this->submit($chapterId, $primera['token']);
        $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();

        self::assertContains('BALANCE_WENT_NEGATIVE', $this->inboxKindsOf($autora['token']));

        $this->clearTheDebtByCorrecting($autora);

        self::assertContains('CORRECTION_UNLOCKED', $this->inboxKindsOf($autora['token']));
    }

    /**
     * La autora corrige la obra de otra persona hasta salir de números rojos.
     *
     * @param array{token: string, userId: string} $autora
     */
    private function clearTheDebtByCorrecting(array $autora, string $patron = 'mecenas'): void
    {
        $mecenas = $this->activatedPerson($patron);
        $workId = $this->createWork($mecenas['token'], 'La obra que salda deudas');
        // Tan caro como el mecenas puede pagar, que es lo que hace falta
        // para que corregirlo salde una deuda y deje margen por encima.
        $chapterId = $this->addChapter($workId, $mecenas['token'], words: 8000, marker: 'Capítulo largo');
        $this->saveQuestionnaire($workId, $mecenas['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);
        $this->openForCorrection($workId, $mecenas['token']);

        $this->start($chapterId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->submit($chapterId, $autora['token']);
        $this->consumeEverything();
    }

    /**
     * Una obra abierta con **dos lectoras dentro a la vez**, que es el camino
     * real al descubierto y el único que hay.
     *
     * Con una sola no se puede: `RN-2` impide empezar una corrección que el
     * autor no pueda pagar. La deuda aparece cuando varias coinciden sobre el
     * mismo capítulo —las dos podían empezar, y solo la primera estaba
     * cubierta—, que es exactamente lo que la ficha predice: `N` lectoras
     * simultáneas dejan al autor hasta en `−(N−1) × p`.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: array{token: string, userId: string}, 3: string, 4: string}
     */
    private function aWorkWithTwoReadersInside(): array
    {
        $autora = $this->activatedPerson('autora');
        $primera = $this->activatedPerson('primera');
        $segunda = $this->activatedPerson('segunda');

        $workId = $this->createWork($autora['token'], 'La obra que endeuda');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 6000);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 50],
        ]);
        $this->openForCorrection($workId, $autora['token']);

        // Las dos empiezan antes de que ninguna entregue: ahí el saldo
        // todavía cubre una, así que a las dos se les deja pasar.
        foreach ([$primera, $segunda] as $lectora) {
            $this->start($chapterId, $lectora['token']);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
            $this->capture();
        }

        $this->consumeEverything();

        return [$autora, $primera, $segunda, $chapterId, $workId];
    }

    private function openForCorrection(string $workId, string $token): void
    {
        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $token, ['accessMode' => 'PUBLIC']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $token, ['status' => 'PUBLISHED']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $token, ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();
    }

    private function start(string $chapterId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function submit(string $chapterId, string $token): string
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{questionId: string, minWords?: int|null}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'answers' => array_map(
                static fn (array $question): array => [
                    'questionId' => $question['questionId'],
                    'text' => implode(' ', array_fill(0, 2100, 'palabra')),
                ],
                $questions,
            ),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['correctionId'];
    }

    private function visibilityOf(string $correctionId): CorrectionVisibility
    {
        /** @var CorrectionRepository $corrections */
        $corrections = self::getContainer()->get(CorrectionRepository::class);
        $correction = $corrections->ofId(CorrectionId::fromString($correctionId));

        self::assertNotNull($correction);

        return $correction->visibility();
    }

    /**
     * @return list<string>
     */
    private function inboxKindsOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/notifications', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{kind: string}> $data */
        $data = $this->payload()['data'];

        return array_map(static fn (array $aviso): string => $aviso['kind'], $data);
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
