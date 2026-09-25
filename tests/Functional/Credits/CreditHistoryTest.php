<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El historial de movimientos (`FEAT-CRD-008`).
 *
 * **Es la invariante del contexto, enseñada**: el saldo es la suma de sus
 * movimientos. Lo que se prueba aquí es justamente eso — que la lista y el
 * saldo cuentan lo mismo— porque en cuanto dejen de hacerlo, la moneda deja
 * de ser creíble.
 */
final class CreditHistoryTest extends EconomyScenario
{
    public function testTheHistoryAddsUpToTheBalance(): void
    {
        [$autora, $lectora] = $this->aDeliveredCorrection();

        foreach ([$autora, $lectora] as $persona) {
            $this->history($persona['token']);
            self::assertResponseIsSuccessful();

            $movimientos = $this->payload()['movements'];
            self::assertNotEmpty($movimientos);

            $suma = array_sum(array_column($movimientos, 'amount'));
            self::assertSame($this->balanceOf($persona['userId']), $suma, 'La lista y el saldo cuentan lo mismo.');

            self::assertSame(
                $this->balanceOf($persona['userId']),
                $movimientos[0]['balanceAfter'],
                'Y el apunte más reciente deja el saldo de ahora.',
            );
        }
    }

    /**
     * Cada apunte dice **por qué texto** se movió el saldo. Un saldo que baja
     * once créditos sin explicación es la opacidad que esta pantalla existe
     * para evitar.
     */
    public function testEachMovementSaysWhatItWasFor(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        $this->history($autora['token'], ['reason' => 'CORRECTION_CHARGED']);

        $cargo = $this->payload()['movements'][0];
        self::assertLessThan(0, $cargo['amount']);
        self::assertSame($correctionId, $cargo['correctionId']);
        self::assertNotNull($cargo['chapterId']);
        self::assertNotNull($cargo['workId']);
        self::assertFalse($cargo['reconstructedPrice']);
    }

    /**
     * `RN-5`: una reversión son **dos apuntes nuevos**, no una edición del
     * original. Es lo que hace que el historial siga cuadrando después.
     */
    public function testAReversalShowsUpAsTwoNewEntries(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->history($lectora['token']);
        $antes = \count($this->payload()['movements']);

        $this->upholdAClaimAbout($correctionId, $autora);

        $this->history($lectora['token']);
        $despues = $this->payload()['movements'];

        self::assertCount($antes + 1, $despues, 'Un apunte más, y el anterior sigue ahí.');
        self::assertSame('CLAIM_REVERSAL_CHARGE', $despues[0]['reason']);
        self::assertLessThan(0, $despues[0]['amount']);
        self::assertSame($correctionId, $despues[0]['correctionId']);

        self::assertSame(
            $this->balanceOf($lectora['userId']),
            array_sum(array_column($despues, 'amount')),
            'Y la suma sigue cuadrando con el saldo.',
        );
    }

    public function testItFiltersByReasonAndPaginates(): void
    {
        [$autora] = $this->aDeliveredCorrection();

        $this->history($autora['token'], ['reason' => 'WELCOME_GRANT']);
        self::assertCount(1, $this->payload()['movements']);
        self::assertSame('WELCOME_GRANT', $this->payload()['movements'][0]['reason']);

        $this->history($autora['token'], ['limit' => '1']);
        self::assertCount(1, $this->payload()['movements']);
    }

    /**
     * `RN-9`: consultar no crea la cuenta de quien todavía no tiene ninguna.
     */
    public function testAnAccountWithoutMovementsAnswersAnEmptyList(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->history($token);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['movements']);
    }

    public function testNobodySeesSomebodyElsesHistory(): void
    {
        $this->client->request('GET', '/api/v1/credits/movements');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array{token: string, userId: string} $autora
     */
    private function upholdAClaimAbout(string $correctionId, array $autora): void
    {
        $moderadora = $this->activatedPerson('moderadora');

        /** @var \LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler $setRole */
        $setRole = self::getContainer()->get(\LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler::class);
        $setRole(new \LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorRole(
            \LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler::CONSOLE,
            $moderadora['userId'],
            \LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel::MODERATOR->value,
            fromConsole: true,
        ));

        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode([
            'targetType' => 'CORRECTION',
            'targetId' => $correctionId,
            'reason' => 'NO_VALUE',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $claimId = (string) $this->payload()['claimId'];

        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderadora['token'],
        ], content: json_encode([
            'decision' => 'UPHELD',
            'motivation' => 'No responde a ninguna de las preguntas.',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    /**
     * @param array<string, string> $query
     */
    private function history(string $token, array $query = []): void
    {
        $this->client->request('GET', '/api/v1/credits/movements', $query, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }
}
