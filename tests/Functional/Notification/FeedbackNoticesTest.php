<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Notification;

use Symfony\Component\HttpFoundation\Response;

/**
 * Los tres avisos del ciclo de una corrección (`FEAT-NOT-006`).
 *
 * Dos de ellos van **al corrector**, y son lo único que la plataforma le
 * devuelve a cambio de haber escrito: sin ellos, corregir es entregar un
 * texto en un buzón y no saber nunca si sirvió de algo.
 *
 * Los consumidores existían desde `FEAT-NOT-001`; dos de los tres no tenían
 * una sola prueba. Esto son esas pruebas, y la de que **ninguno lleva una
 * línea de texto** — ni de la corrección ni de la respuesta del autor.
 */
final class FeedbackNoticesTest extends NotificationScenario
{
    /**
     * `RN-1`: contestar avisa a quien corrigió, y solo a quien corrigió.
     */
    public function testReplyingNotifiesTheReaderAndNotTheAuthor(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->reply($correctionId, $autora['token'], 'Gracias, me sirve mucho lo del ritmo.');

        $aviso = $this->notice($lectora['token'], 'CORRECTION_REPLIED');
        self::assertSame($correctionId, $aviso['payload']['correctionId']);
        self::assertNotContains(
            'CORRECTION_REPLIED',
            $this->kinds($autora['token']),
            'Quien contesta ya sabe que ha contestado.',
        );
    }

    /**
     * `RN-2`: el aviso dice que hay algo que leer. **Nada de lo que dice.**.
     */
    public function testTheReplyNoticeCarriesNoText(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->reply($correctionId, $autora['token'], 'Un secreto sobre el final de mi novela.');

        $aviso = $this->notice($lectora['token'], 'CORRECTION_REPLIED');

        self::assertStringNotContainsString(
            'secreto',
            json_encode($aviso, \JSON_THROW_ON_ERROR),
            'Ni una línea de la respuesta del autor.',
        );
    }

    /**
     * `RN-3`: sustituir la respuesta no vuelve a anunciar. La decisión es de
     * quien publica el hecho, y aquí se comprueba que se respeta.
     */
    public function testReplacingTheReplyDoesNotAnnounceAgain(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->reply($correctionId, $autora['token'], 'Primera respuesta.');
        $this->reply($correctionId, $autora['token'], 'Mejor dicho de otra forma.');

        self::assertSame(
            ['CORRECTION_REPLIED'],
            array_values(array_filter(
                $this->kinds($lectora['token']),
                static fn (string $kind): bool => 'CORRECTION_REPLIED' === $kind,
            )),
            'Un aviso, no dos.',
        );
    }

    /**
     * `RN-1`: valorar como útil avisa a quien corrigió.
     */
    public function testRatingAsHelpfulNotifiesTheReader(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->rate($correctionId, $autora['token'], helpful: true);

        self::assertSame($correctionId, $this->notice($lectora['token'], 'CORRECTION_RATED')['payload']['correctionId']);
    }

    /**
     * `RN-4`: **valorar como no útil no avisa de nada.**.
     *
     * Decirle a alguien que su trabajo no ha servido es una crueldad sin
     * función: no hay nada que pueda hacer con esa información.
     */
    public function testRatingAsUnhelpfulNotifiesNobody(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->rate($correctionId, $autora['token'], helpful: false);

        self::assertNotContains('CORRECTION_RATED', $this->kinds($lectora['token']));
    }

    /**
     * `RN-6`: el aviso del autor **se retira solo** cuando abre la
     * corrección.
     *
     * Y de paso fija lo que `FEAT-NOT-006` vino a corregir: el hecho que
     * dispara esto lleva el identificador **del autor**, aunque el campo se
     * llamara `readerId`. Quien lo consumía acertaba por un nombre que decía
     * lo contrario.
     */
    public function testOpeningTheCorrectionRetiresTheAuthorsNotice(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        self::assertSame(1, $this->unreadCount($autora['token']));

        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertSame(0, $this->unreadCount($autora['token']), 'Lo ha leído: el aviso sobra.');
        self::assertSame(
            ['CORRECTION_RECEIVED'],
            $this->kinds($autora['token']),
            'Retirado no es borrado: la fila sigue, leída.',
        );
    }

    private function reply(string $correctionId, string $token, string $body): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/reply', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();
    }

    private function rate(string $correctionId, string $token, bool $helpful): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/rating', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['helpful' => $helpful], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();
    }
}
