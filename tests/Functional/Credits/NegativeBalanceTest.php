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
    /** @var array{token: string, userId: string}|null */
    private ?array $moderadora = null;

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
     * `RN-8b` y [`FEAT-MOD-006`](../../../docs/features/moderation/FEAT-MOD-006-sanctions.md)
     * `RN-9`: durante una suspensión parcial la deuda deja de retener.
     *
     * La trampa que esto evita: quien está suspendido no puede corregir, y
     * corregir es la única forma de saldar la deuda. Retenerle mientras tanto
     * lo que ya le entregaron sería exigirle justo lo que le hemos prohibido.
     */
    public function testAPartialSuspensionReleasesWhatTheDebtWasHolding(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        $this->submit($chapterId, $primera['token']);
        $correctionId = $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();

        self::assertLessThan(0, (int) $this->balanceOf($autora['userId']));
        self::assertSame(CorrectionVisibility::LOCKED, $this->visibilityOf($correctionId));

        $this->suspendPartially($autora['userId']);

        // El saldo **no** se toca: la contabilidad no se congela, lo que se
        // congela son sus efectos.
        self::assertLessThan(0, (int) $this->balanceOf($autora['userId']), 'Sigue debiendo lo mismo.');
        self::assertSame(CorrectionVisibility::VISIBLE, $this->visibilityOf($correctionId), 'Pero ya puede leerla.');
    }

    /**
     * La otra mitad de `RN-8b`: se congela **la retención, no la
     * corregibilidad**.
     *
     * Si durante la sanción sus capítulos volvieran a admitir correcciones,
     * cada una cobrada ahondaría la deuda — y la regla dice «ni crece» en la
     * misma frase en que dice «ni bloquea nada».
     */
    public function testTheFrozenDebtStillKeepsNewCorrectionsOut(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        $this->submit($chapterId, $primera['token']);
        $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();

        $this->suspendPartially($autora['userId']);

        $tercera = $this->activatedPerson('tercera');
        $this->start($chapterId, $tercera['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT, 'Congelar no es perdonar.');
        self::assertSame('CHAPTER_NOT_TAKING_CORRECTIONS', $this->payload()['code']);
    }

    /**
     * Y lo que llega **durante** el plazo tampoco se retiene.
     *
     * Aquí se ve por qué `Feedback` guarda la fecha y no solo el aviso: esta
     * corrección se entrega después de que el evento de congelar haya pasado
     * y se haya olvidado. Sin la fila, llegaría bloqueada.
     */
    public function testACorrectionDeliveredDuringTheFreezeArrivesUnlocked(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        // La primera entrega con el saldo todavía a flote.
        $this->submit($chapterId, $primera['token']);
        $this->consumeEverything();
        self::assertGreaterThanOrEqual(0, (int) $this->balanceOf($autora['userId']));

        // Se la sanciona **antes** de caer en rojo.
        $this->suspendPartially($autora['userId']);

        // Y ahora la segunda, que ya estaba dentro, entrega y la deja en rojo.
        $correctionId = $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();

        self::assertLessThan(0, (int) $this->balanceOf($autora['userId']));
        self::assertSame(CorrectionVisibility::VISIBLE, $this->visibilityOf($correctionId));
    }

    /**
     * Levantar la sanción devuelve la deuda a la vida, pero **solo hacia
     * adelante** (`RN-11`).
     *
     * Lo que la autora ya pudo leer, leído está. Volver a cerrarlo sería
     * reescribir el pasado, y la regla no admite excepciones por el motivo
     * por el que se abrió.
     */
    public function testLiftingTheSanctionDoesNotCloseAgainWhatWasAlreadyRead(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        $this->submit($chapterId, $primera['token']);
        $correctionId = $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();

        $sanctionId = $this->suspendPartially($autora['userId']);
        self::assertSame(CorrectionVisibility::VISIBLE, $this->visibilityOf($correctionId));

        $this->liftSanction($sanctionId);

        self::assertLessThan(0, (int) $this->balanceOf($autora['userId']), 'La deuda nunca se fue.');
        self::assertSame(CorrectionVisibility::VISIBLE, $this->visibilityOf($correctionId));
    }

    /**
     * Y una suspensión **total** no congela nada.
     *
     * No hay nadie dentro a quien retenerle la lectura, y perdonarle la
     * consecuencia a quien hizo algo peor sería premiar la gravedad.
     */
    public function testAFullSuspensionDoesNotFreezeTheDebt(): void
    {
        [$autora, $primera, $segunda, $chapterId] = $this->aWorkWithTwoReadersInside();

        $this->submit($chapterId, $primera['token']);
        $correctionId = $this->submit($chapterId, $segunda['token']);
        $this->consumeEverything();

        $this->impose([
            'userId' => $autora['userId'],
            'type' => 'FULL_SUSPENSION',
            'reason' => 'Reincidencia grave',
        ]);

        self::assertSame(CorrectionVisibility::LOCKED, $this->visibilityOf($correctionId));
    }

    /**
     * Impone una suspensión parcial y deja que todos los contextos la
     * procesen.
     *
     * @return string el identificador de la sanción, para poder levantarla
     */
    private function suspendPartially(string $userId): string
    {
        return $this->impose([
            'userId' => $userId,
            'type' => 'PARTIAL_SUSPENSION',
            'reason' => 'Comentarios ofensivos',
            'duration' => 'ONE_WEEK',
        ]);
    }

    /**
     * @param array<string, string> $body
     *
     * @return string el identificador de la sanción impuesta
     */
    private function impose(array $body): string
    {
        $this->client->request('POST', '/api/v1/admin/sanctions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->moderadora()['token'],
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $sanctionId = (string) $this->payload()['sanctionId'];

        $this->capture();
        $this->consumeEverything();

        return $sanctionId;
    }

    private function liftSanction(string $sanctionId): void
    {
        $this->client->request('POST', \sprintf('/api/v1/admin/sanctions/%s/lift', $sanctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->moderadora()['token'],
        ], content: json_encode(['reason' => 'Se aclaró el malentendido'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    /**
     * La misma moderadora en todas las llamadas de un test: registrarla dos
     * veces chocaría con su propio correo.
     *
     * @return array{token: string, userId: string}
     */
    private function moderadora(): array
    {
        return $this->moderadora ??= $this->moderator('moderadora');
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
