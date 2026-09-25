<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Feedback;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contestar (`FEAT-FBK-005`) y valorar (`FEAT-FBK-006`) una corrección.
 *
 * **Es lo único que la plataforma le devuelve a quien corrigió.** Sin esto,
 * corregir es escribir en un buzón: se entrega, se cobra, y no se sabe si
 * sirvió de algo.
 */
final class AnswerACorrectionTest extends EconomyScenario
{
    public function testTheAuthorRepliesAndTheWriterSeesIt(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->reply($correctionId, $autora['token'], 'Gracias: lo del ritmo lo he visto igual.');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->correction($correctionId, $lectora['token']);
        self::assertSame('Gracias: lo del ritmo lo he visto igual.', $this->payload()['reply']);

        $this->unreadCount($lectora['token']);
        self::assertSame(1, $this->payload()['unreadCount'], 'Y se entera.');
    }

    /**
     * `RN-2`: volver a enviarla sustituye la que había, y no vuelve a avisar.
     * Recibir tres notificaciones porque alguien corrige sus erratas es ruido.
     */
    public function testReplyingTwiceLeavesOneReplyAndOneNotice(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->reply($correctionId, $autora['token'], 'Primera versión.');
        $this->capture();
        $this->reply($correctionId, $autora['token'], 'Segunda, sin la errata.');
        $this->capture();
        $this->consumeEverything();

        $this->correction($correctionId, $autora['token']);
        self::assertSame('Segunda, sin la errata.', $this->payload()['reply']);

        $this->unreadCount($lectora['token']);
        self::assertSame(1, $this->payload()['unreadCount'], 'Un aviso, no dos.');
    }

    /**
     * `RN-6`: lo que se dijo deja de estar, y no se avisa de la retirada.
     */
    public function testTheAuthorCanWithdrawTheReply(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        $this->reply($correctionId, $autora['token'], 'Algo que luego me arrepiento de haber escrito.');
        $this->deleteReply($correctionId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->correction($correctionId, $autora['token']);
        self::assertNull($this->payload()['reply']);

        $this->deleteReply($correctionId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT, 'Retirar lo que no hay no es un error.');
    }

    /**
     * `FEAT-FBK-006` `RN-2` y `RN-7`: binaria, cambiable, y solo la primera
     * positiva avisa.
     */
    public function testRatingIsBinaryAndOnlyTheFirstPositiveOneIsAnnounced(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $this->correction($correctionId, $autora['token']);
        self::assertNull($this->payload()['helpful'], 'Sin valorar es un tercer estado, y es el inicial.');

        $this->rate($correctionId, $autora['token'], true);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        $this->unreadCount($lectora['token']);
        self::assertSame(1, $this->payload()['unreadCount']);

        $this->rate($correctionId, $autora['token'], false);
        self::assertSame([], $this->capture('FeedbackRatedPositively'), 'Cambiarla a «no útil» no anuncia nada.');
        $this->consumeEverything();

        $this->correction($correctionId, $lectora['token']);
        self::assertFalse($this->payload()['helpful'], 'Y quien corrigió lo ve, también cuando es que no.');

        $this->unreadCount($lectora['token']);
        self::assertSame(1, $this->payload()['unreadCount'], 'Sin un segundo aviso.');
    }

    public function testRatingMovesNoCredits(): void
    {
        [$autora, $lectora, $correctionId] = $this->aDeliveredCorrection();

        $antesAutora = $this->balanceOf($autora['userId']);
        $antesLectora = $this->balanceOf($lectora['userId']);

        $this->rate($correctionId, $autora['token'], true);
        $this->capture();
        $this->consumeEverything();

        self::assertSame($antesAutora, $this->balanceOf($autora['userId']));
        self::assertSame($antesLectora, $this->balanceOf($lectora['userId']), 'Valorar no es pagar: eso es la propina.');
    }

    public function testOnlyTheAuthorOfTheWorkAnswers(): void
    {
        [, $lectora, $correctionId] = $this->aDeliveredCorrection();
        $extrana = $this->activatedPerson('extrana');

        $this->reply($correctionId, $lectora['token'], 'Me contesto a mí misma.');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'Ni quien la escribió.');

        $this->rate($correctionId, $extrana['token'], true);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/rating', $correctionId));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAnEmptyReplyIsRefused(): void
    {
        [$autora, , $correctionId] = $this->aDeliveredCorrection();

        $this->reply($correctionId, $autora['token'], '   ');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('EMPTY_REPLY', $this->payload()['code']);
    }

    private function reply(string $correctionId, string $token, string $body): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/reply', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));
    }

    private function deleteReply(string $correctionId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/corrections/%s/reply', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function rate(string $correctionId, string $token, bool $helpful): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/corrections/%s/rating', $correctionId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['helpful' => $helpful], \JSON_THROW_ON_ERROR));
    }

    private function correction(string $correctionId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/corrections/%s', $correctionId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function unreadCount(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/notifications/unread-count', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }
}
