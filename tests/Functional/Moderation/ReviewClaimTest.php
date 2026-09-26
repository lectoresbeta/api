<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

/**
 * Resolver una reclamación (`FEAT-MOD-002`) y lo que eso desencadena
 * (`FEAT-MOD-003`).
 *
 * Es el único sitio de la plataforma donde una decisión humana **le quita
 * créditos a alguien que trabajó** y retira una obra de la vista, así que lo
 * que se prueba aquí es sobre todo lo que impide que eso ocurra a la ligera:
 * quién no puede decidir, qué no se puede deshacer y qué le cuesta a quien
 * reclama en falso.
 *
 * Y una afirmación que atraviesa toda la arquitectura: `Moderation` **no
 * aplica ningún efecto**. Publica qué se ha decidido, y son `Work` y
 * `Credits` los que deciden qué significa en su modelo. Por eso casi todas
 * las comprobaciones de efecto llegan después de `consumeEverything()`.
 */
final class ReviewClaimTest extends EconomyScenario
{
    /**
     * El camino entero: alguien reclama, un moderador estima, la obra se
     * bloquea y el autor recibe un correo que le dice dónde recurrir.
     */
    public function testUpholdingAClaimAboutAWorkBlocksItAndTellsTheAuthorWhereToAppeal(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $moderadora = $this->moderator('moderadora');
        $workId = $this->publishedWork($autora);

        $this->work($workId, $lectora['token']);
        self::assertResponseIsSuccessful('Antes del bloqueo, cualquiera la lee.');

        $claimId = $this->claimAbout($lectora['token'], 'WORK', $workId);

        $this->queue($moderadora['token']);
        self::assertResponseIsSuccessful();
        self::assertSame([$claimId], array_column($this->payload()['claims'], 'claimId'));

        $this->review($moderadora['token'], $claimId, 'UPHELD', 'La escena incumple las normas y no está etiquetada.');
        self::assertResponseIsSuccessful();
        self::assertSame('UPHELD', $this->payload()['status']);
        $this->capture();
        $this->consumeEverything();

        // El correo, antes que nada: el recolector del mailer se vacía con
        // cada petición, así que mirarlo después de las comprobaciones de
        // abajo sería mirar una lista vacía.
        $notice = $this->lastEmailTo($this->address('autora'));
        self::assertStringContainsString('reclamaciones@lectoresbeta.com', $notice, 'Un recurso que no se sabe dónde presentar no existe.');
        self::assertStringContainsString('La obra reclamada', $notice);

        // `Work` ejecuta el bloqueo, no `Moderation`.
        $this->work($workId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Deja de existir para el resto.');

        $this->work($workId, $autora['token']);
        self::assertResponseIsSuccessful('Su autora la sigue viendo.');
        self::assertTrue($this->payload()['blocked'], 'Y marcada.');

        $this->myClaims($lectora['token']);
        self::assertSame('UPHELD', $this->payload()['claims'][0]['status']);
    }

    /**
     * `RN-1`, y la parte que la ficha subraya: **no basta con rechazar la
     * acción, no debe verlas**. Ver el expediente ya es enterarse de quién te
     * denunció.
     */
    public function testTheQueueHidesWhatTheModeratorIsPartyTo(): void
    {
        $autora = $this->activatedPerson('autora');
        $moderadora = $this->moderator('moderadora');
        $otra = $this->activatedPerson('otra');

        // Una que presentó ella misma, y otra que señala su obra.
        $ajena = $this->publishedWork($autora);
        $propia = $this->publishedWork($moderadora, 'La obra de la moderadora');

        $suya = $this->claimAbout($moderadora['token'], 'WORK', $ajena);
        $contraElla = $this->claimAbout($otra['token'], 'WORK', $propia);

        $this->queue($moderadora['token']);
        self::assertSame([], $this->payload()['claims'], 'Ni la que presentó ni la que la señala.');

        $this->review($moderadora['token'], $suya, 'REJECTED', 'Me la quito de encima.');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('CLAIM_MODERATOR_IS_PARTY', $this->payload()['code']);

        $this->review($moderadora['token'], $contraElla, 'REJECTED', 'No hay nada que ver aquí.');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Y menos todavía una que va contra ella.');

        // Otro moderador sí las ve: lo que se excluye es a quien es parte.
        $segunda = $this->moderator('segunda');
        $this->queue($segunda['token']);
        self::assertCount(2, $this->payload()['claims']);
    }

    /**
     * `RN-2`. Un expediente sin motivo no se puede auditar ni se puede
     * defender si alguien discute la decisión, que con créditos de por medio
     * acaba pasando.
     */
    public function testADecisionWithoutAWrittenMotivationIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $moderadora = $this->moderator('moderadora');
        $claimId = $this->claimAbout($lectora['token'], 'WORK', $this->publishedWork($autora));

        $this->review($moderadora['token'], $claimId, 'UPHELD', '   ');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CLAIM_MOTIVATION_REQUIRED', $this->payload()['code']);
    }

    /**
     * `RN-3`: el estado no retrocede, y `RN-8`: dos moderadores no resuelven
     * la misma. Lo segundo se ve como lo primero desde fuera, que es
     * exactamente lo que el bloqueo de la fila produce.
     */
    public function testAResolvedClaimDoesNotGoBack(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $moderadora = $this->moderator('moderadora');
        $segunda = $this->moderator('segunda');
        $claimId = $this->claimAbout($lectora['token'], 'WORK', $this->publishedWork($autora));

        $this->review($moderadora['token'], $claimId, 'REJECTED', 'La obra está correctamente etiquetada.');
        self::assertResponseIsSuccessful();

        $this->review($segunda['token'], $claimId, 'UPHELD', 'Yo lo veo de otra manera.');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CLAIM_ALREADY_RESOLVED', $this->payload()['code']);
    }

    /**
     * Desestimar no es gratis para quien reclamó (`FEAT-MOD-001` `RN-6b`).
     * Sin esto, reclamar en falso sale gratis y el botón de denunciar es una
     * forma barata de no pagar una corrección.
     */
    public function testDismissingAClaimCostsTheReporterTheButton(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $moderadora = $this->moderator('moderadora');
        $claimId = $this->claimAbout($lectora['token'], 'WORK', $this->publishedWork($autora));

        $this->review($moderadora['token'], $claimId, 'REJECTED', 'No hay nada reprochable en el texto.');
        self::assertResponseIsSuccessful();

        $this->claim($lectora['token'], 'WORK', $this->publishedWork($autora, 'Otra obra'), 'SPAM');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('CLAIM_BLOCKED', $this->payload()['code']);
        self::assertNotEmpty($this->payload()['blockedUntil'], 'Y con fecha.');
    }

    /**
     * La reversión, que es la consecuencia más dura de todas: **dos apuntes
     * nuevos**, nunca una edición del original, y por el importe que se
     * cobró.
     */
    public function testUpholdingAClaimAboutACorrectionReversesWhatWasCharged(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $moderadora = $this->moderator('moderadora');

        $autoraTrasPagar = $this->balanceOf($autora['userId']);
        $lectoraTrasCobrar = $this->balanceOf($lectora['userId']);
        self::assertNotNull($autoraTrasPagar);
        self::assertNotNull($lectoraTrasCobrar);

        $claimId = $this->claimAbout($autora['token'], 'CORRECTION', $correctionId, 'NO_VALUE');

        $this->review($moderadora['token'], $claimId, 'UPHELD', 'No responde a ninguna de las preguntas del cuestionario.');
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        $cobrado = $lectoraTrasCobrar - $this->balanceOf($lectora['userId']);

        self::assertGreaterThan(0, $cobrado, 'Se le retira al corrector lo que cobró.');
        self::assertSame($autoraTrasPagar + $cobrado, $this->balanceOf($autora['userId']), 'Y se le devuelve a la autora lo mismo.');
    }

    /**
     * La entrega repetida es la norma, no la excepción: el transporte no
     * promete entregar una sola vez. Revertir dos veces dejaría al corrector
     * pagando dos veces la misma corrección.
     */
    public function testTheReversalHappensOnlyOnceHoweverOftenTheFactArrives(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $moderadora = $this->moderator('moderadora');

        $claimId = $this->claimAbout($autora['token'], 'CORRECTION', $correctionId, 'NO_VALUE');

        $this->review($moderadora['token'], $claimId, 'UPHELD', 'Es texto copiado de otra corrección.');
        $upheld = $this->capture('ClaimUpheld');
        self::assertCount(1, $upheld);
        $this->consumeEverything();

        $despues = $this->balanceOf($lectora['userId']);

        $this->deliver($upheld);
        $this->deliver($upheld);

        self::assertSame($despues, $this->balanceOf($lectora['userId']), 'La segunda entrega no cobra nada.');
    }

    /**
     * `RN-9` de `FEAT-MOD-003`: al tercer capítulo bloqueado cae la obra
     * entera. El umbral existe para que una obra a la que le van faltando
     * capítulos no siga publicada ofreciendo una lectura llena de huecos.
     */
    public function testThreeBlockedChaptersBringTheWholeWorkDown(): void
    {
        $autora = $this->activatedPerson('autora');
        $moderadora = $this->moderator('moderadora');

        $workId = $this->createWork($autora['token'], 'La novela con capítulos');
        $chapters = [
            $this->addChapter($workId, $autora['token'], words: 400, marker: 'Uno'),
            $this->addChapter($workId, $autora['token'], words: 400, marker: 'Dos'),
            $this->addChapter($workId, $autora['token'], words: 400, marker: 'Tres'),
            $this->addChapter($workId, $autora['token'], words: 400, marker: 'Cuatro'),
        ];
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        foreach (\array_slice($chapters, 0, 2) as $index => $chapterId) {
            $this->upholdAChapterClaim($moderadora['token'], $chapterId, \sprintf('quien%d', $index));

            $this->work($workId, $this->activatedPerson('curiosa'.$index)['token']);
            self::assertResponseIsSuccessful('Con uno y con dos capítulos bloqueados, la obra sigue en pie.');
        }

        $this->upholdAChapterClaim($moderadora['token'], $chapters[2], 'tercera');

        $this->work($workId, $this->activatedPerson('curiosaFinal')['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Al tercero, la obra entera.');
    }

    public function testOnlyAModeratorGetsNearTheQueue(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->queue($persona['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', '/api/v1/admin/claims');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('POST', '/api/v1/admin/claims/'.$this->eventId().'/review');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAnUnknownClaimIsNotFound(): void
    {
        $moderadora = $this->moderator('moderadora');

        $this->review($moderadora['token'], $this->eventId(), 'UPHELD', 'Da igual lo que escriba.');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('CLAIM_NOT_FOUND', $this->payload()['code']);
    }

    private function upholdAChapterClaim(string $moderatorToken, string $chapterId, string $local): void
    {
        $claimId = $this->claimAbout($this->activatedPerson($local)['token'], 'CHAPTER', $chapterId, 'OFFENSIVE');

        $this->review($moderatorToken, $claimId, 'UPHELD', 'El capítulo incumple las normas.');
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function claimAbout(string $token, string $targetType, string $targetId, string $reason = 'OFFENSIVE'): string
    {
        $this->claim($token, $targetType, $targetId, $reason);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return (string) $this->payload()['claimId'];
    }

    private function claim(string $token, string $targetType, string $targetId, string $reason): void
    {
        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'reason' => $reason,
        ], \JSON_THROW_ON_ERROR));
    }

    private function review(string $token, string $claimId, string $decision, string $motivation): void
    {
        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => $decision, 'motivation' => $motivation], \JSON_THROW_ON_ERROR));
    }

    private function queue(string $token): void
    {
        $this->client->request('GET', '/api/v1/admin/claims', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function myClaims(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/claims', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function work(string $workId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function lastEmailTo(string $address): string
    {
        foreach (array_reverse(self::getMailerMessages()) as $message) {
            if ($message instanceof Email && str_contains($message->getTo()[0]?->getAddress() ?? '', $address)) {
                return $message->toString();
            }
        }

        self::fail(\sprintf('No se ha enviado ningún correo a %s.', $address));
    }
}
