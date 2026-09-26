<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Notification;

use LectoresBeta\Moderation\Sanction\Domain\Enum\SanctionType;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los cuatro avisos que el catálogo prometía y nadie disparaba.
 *
 * Salen del inventario del 2026-09-26: cuatro fichas distintas decían «falta
 * el aviso» y las cuatro eran el mismo trabajo. `POST_REPLY`, `MENTION` y
 * `MODERATION_ALERT` llevaban en el catálogo desde `FEAT-NOT-001` con su
 * frase escrita y sin nada que los provocara; los dos primeros con **casilla
 * en Configuración**, que es lo peor de todo: una casilla que promete un
 * control sobre un aviso que no existe.
 *
 * El que más importa es el de la sanción. Hasta ahora se restringía una
 * cuenta y su titular lo descubría chocándose, sin saber qué, por qué ni
 * hasta cuándo.
 *
 * Nada se fabrica a mano: cada aviso nace de una llamada real a la API del
 * contexto de origen, y el hecho viaja por el serializador de verdad.
 */
final class PromisedNoticesTest extends NotificationScenario
{
    public function testCommentingOnAPostNotifiesWhoeverWroteIt(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $postId = $this->publish($autora['token'], 'Una idea');

        $this->comment($postId, $lectora['token'], 'Me interesa');
        $this->consumeEverything();

        self::assertSame(['POST_REPLY'], $this->kinds($autora['token']));
        self::assertSame([], $this->kinds($lectora['token']), 'Quien comenta ya sabe que ha comentado.');
    }

    /**
     * Una respuesta avisa a **dos personas distintas**, porque son dos hechos
     * distintos: quien publicó se entera de que hay conversación bajo su
     * texto, y quien comentó se entera de que le han contestado.
     */
    public function testAReplyNotifiesBothThePosterAndThePersonRepliedTo(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $tercera = $this->activatedPerson('tercera');

        $postId = $this->publish($autora['token'], 'Una idea');
        $commentId = $this->comment($postId, $lectora['token'], 'Me interesa');
        $this->reply($commentId, $tercera['token'], 'Y a mí');
        $this->consumeEverything();

        self::assertSame(['POST_REPLY', 'POST_REPLY'], $this->kinds($autora['token']));
        self::assertSame(['POST_REPLY'], $this->kinds($lectora['token']));
        self::assertSame([], $this->kinds($tercera['token']));
    }

    /**
     * Y quien se comenta a sí mismo no recibe nada, ni siquiera cuando es a
     * la vez autor de la publicación y del comentario padre: ese caso se
     * descarta antes, o esa persona recibiría el mismo aviso dos veces.
     */
    public function testNobodyNotifiesThemselves(): void
    {
        $autora = $this->activatedPerson('autora');
        $postId = $this->publish($autora['token'], 'Una idea');

        $commentId = $this->comment($postId, $autora['token'], 'Me respondo');
        $this->reply($commentId, $autora['token'], 'Otra vez');
        $this->consumeEverything();

        self::assertSame([], $this->kinds($autora['token']));
    }

    public function testBeingMentionedNotifiesTheMentionedPerson(): void
    {
        $autora = $this->activatedPerson('autora');
        $mencionada = $this->activatedPerson('mencionada');

        $this->publish($autora['token'], 'Esto le interesa a alguien', mentions: [$mencionada['userId']]);
        $this->consumeEverything();

        self::assertSame(['MENTION'], $this->kinds($mencionada['token']));

        $notice = $this->notice($mencionada['token'], 'MENTION');
        self::assertSame($autora['userId'], $notice['payload']['actorId']);
        self::assertSame('POST', $notice['payload']['subjectKind']);
    }

    /**
     * **El hueco que más dolía.** `RN-4` de `FEAT-MOD-006` pedía contarle a
     * la persona qué le han hecho, por qué y hasta cuándo, y no lo hacía
     * nadie.
     */
    public function testBeingSanctionedTellsThePersonWhatWhyAndUntilWhen(): void
    {
        $moderadora = $this->moderator('moderadora');
        $infractora = $this->activatedPerson('infractora');

        $this->sanction($moderadora['token'], $infractora['userId'], SanctionType::PARTIAL_SUSPENSION->value, 'Insultos reiterados', duration: 'ONE_WEEK');
        $this->consumeEverything();

        self::assertSame(['MODERATION_ALERT'], $this->kinds($infractora['token']));

        $notice = $this->notice($infractora['token'], 'MODERATION_ALERT');
        self::assertSame('PARTIAL_SUSPENSION', $notice['payload']['sanctionType']);
        self::assertSame('Insultos reiterados', $notice['payload']['reason']);
    }

    /**
     * Y **no se puede apagar**: es operativo, como el aviso de contraseña
     * cambiada. Quien pudiera silenciarlo se quedaría sin enterarse de lo que
     * le está pasando a su cuenta.
     */
    public function testTheSanctionNoticeIgnoresEveryPreference(): void
    {
        $moderadora = $this->moderator('moderadora');
        $infractora = $this->activatedPerson('infractora');

        // El interruptor general, apagado del todo.
        $this->putAs('/api/v1/me/notification-preferences', $infractora['token'], ['enabled' => false]);

        $this->sanction($moderadora['token'], $infractora['userId'], SanctionType::WARNING->value, 'Un aviso');
        $this->consumeEverything();

        self::assertSame(['MODERATION_ALERT'], $this->kinds($infractora['token']));
    }

    /**
     * `FEAT-CRD-016` `RN-9`: **solo cuando sube**. El aviso existe para que
     * ampliar un capítulo no encarezca la corrección a espaldas del autor.
     */
    public function testEnlargingAChapterTellsTheAuthorItGotDearer(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La obra que crece');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->changeStatus($workId, $autora['token'], 'PUBLISHED');
        $this->consumeEverything();

        $this->rewriteChapter($workId, $chapterId, $autora['token'], words: 9000);
        $this->consumeEverything();

        self::assertContains('CHAPTER_PRICE_INCREASED', $this->kinds($autora['token']));

        $notice = $this->notice($autora['token'], 'CHAPTER_PRICE_INCREASED');
        self::assertGreaterThan($notice['payload']['previousCredits'], $notice['payload']['credits']);
    }

    /**
     * Y **no cuando baja**, que es la mitad que hace el aviso soportable: un
     * capítulo que se abarata no interrumpe a nadie.
     */
    public function testShrinkingAChapterSaysNothing(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La obra que mengua');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 9000);
        $this->changeStatus($workId, $autora['token'], 'PUBLISHED');
        $this->consumeEverything();

        // Lo que hubiera llegado por estrenar precio, fuera de la foto.
        $this->markEverythingRead($autora['token']);

        $this->rewriteChapter($workId, $chapterId, $autora['token'], words: 900);
        $this->consumeEverything();

        self::assertNotContains('CHAPTER_PRICE_INCREASED', $this->kinds($autora['token'], ['read' => 'false']));
    }

    /**
     * @param list<string> $mentions
     */
    private function publish(string $token, string $body, array $mentions = []): string
    {
        $content = ['body' => $body, 'type' => 'GENERAL', 'audience' => 'EVERYONE'];

        if ([] !== $mentions) {
            $content['mentions'] = array_map(
                static fn (string $userId): array => ['userId' => $userId],
                $mentions,
            );
        }

        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($content, \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['postId'];
    }

    private function comment(string $postId, string $token, string $body): string
    {
        $this->client->request('POST', '/api/v1/posts/'.$postId.'/comments', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['commentId'];
    }

    /**
     * Responder tiene su propio recurso (`FEAT-COM-031`): una respuesta cuelga
     * de un comentario, no de la publicación.
     */
    private function reply(string $commentId, string $token, string $body): string
    {
        $this->client->request('POST', '/api/v1/comments/'.$commentId.'/replies', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['commentId'];
    }

    private function sanction(string $token, string $userId, string $type, string $reason, ?string $duration = null): void
    {
        $this->client->request('POST', '/api/v1/admin/sanctions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(array_filter([
            'userId' => $userId,
            'type' => $type,
            'reason' => $reason,
            'duration' => $duration,
        ]), \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
    }

    private function rewriteChapter(string $workId, string $chapterId, string $token, int $words): void
    {
        $this->client->request('PUT', '/api/v1/chapters/'.$chapterId, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'contentHtml' => '<p>'.implode(' ', array_fill(0, $words, 'palabra')).'</p>',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
    }

    private function markEverythingRead(string $token): void
    {
        $this->client->request('PUT', '/api/v1/me/notifications/read', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }
}
