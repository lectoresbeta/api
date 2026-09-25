<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La conversación con el moderador (`FEAT-MOD-009`).
 *
 * **Tiene forma de estrella, no de sala.** Cada parte tiene un hilo privado
 * con moderación y no sabe qué dice la otra, ni siquiera si hay otra. No es
 * una restricción técnica: poner a denunciante y denunciado a discutir
 * crearía el conflicto que la moderación existe para evitar.
 *
 * La ficha lo dice sin rodeos: `thread_party` es el campo del que depende
 * toda la privacidad de esta funcionalidad, y **el que conviene cubrir con
 * pruebas que intenten leer el hilo ajeno**. Eso es lo que hacen las de aquí.
 */
final class ClaimConversationTest extends EconomyScenario
{
    public function testTheModeratorOpensAThreadAndThePartyAnswers(): void
    {
        $caso = $this->aClaim();

        $this->write($caso['moderador']['token'], $caso['claimId'], 'REPORTER', '¿Puedes concretar qué te ofendió?');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->thread($caso['reclamante']['token'], $caso['claimId']);
        self::assertSame('MODERATION', $this->payload()['messages'][0]['from']);
        self::assertSame('¿Puedes concretar qué te ofendió?', $this->payload()['messages'][0]['body']);

        $this->reply($caso['reclamante']['token'], $caso['claimId'], 'Lo que escribió en el tercer comentario');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->thread($caso['reclamante']['token'], $caso['claimId']);
        self::assertCount(2, $this->payload()['messages']);
        self::assertSame('ME', $this->payload()['messages'][1]['from']);
    }

    /**
     * **La prueba que sostiene la funcionalidad entera.** Cada parte ve lo
     * suyo y **nada de lo de la otra**: ni el mensaje, ni el hecho de que
     * exista.
     */
    public function testNeitherPartyCanReadTheOtherThread(): void
    {
        $caso = $this->aClaim();

        $this->write($caso['moderador']['token'], $caso['claimId'], 'REPORTER', 'Pregunta para quien reclama');
        $this->write($caso['moderador']['token'], $caso['claimId'], 'SUBJECT', 'Pregunta para el reclamado');

        $this->thread($caso['reclamante']['token'], $caso['claimId']);
        self::assertCount(1, $this->payload()['messages']);
        self::assertSame('Pregunta para quien reclama', $this->payload()['messages'][0]['body']);
        self::assertStringNotContainsString(
            'Pregunta para el reclamado',
            (string) $this->client->getResponse()->getContent(),
            'Y el mensaje ajeno no llega al cliente siquiera.',
        );

        $this->thread($caso['reclamado']['token'], $caso['claimId']);
        self::assertCount(1, $this->payload()['messages']);
        self::assertSame('Pregunta para el reclamado', $this->payload()['messages'][0]['body']);
    }

    /**
     * Para quien no es parte, la reclamación **no existe**. Un permiso
     * denegado le confirmaría que hay un expediente abierto, que ya es
     * información sobre otras personas.
     */
    public function testAStrangerDoesNotEvenLearnThatTheClaimExists(): void
    {
        $caso = $this->aClaim();
        $extrana = $this->activatedPerson('extrana');

        $this->write($caso['moderador']['token'], $caso['claimId'], 'REPORTER', 'Algo');

        $this->thread($extrana['token'], $caso['claimId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('CLAIM_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * `RN-2`: una parte **no abre conversación por su cuenta**. Si pudiera,
     * la cola del moderador se llenaría de alegatos no solicitados y el
     * expediente dejaría de ser un procedimiento.
     */
    public function testAPartyCannotStartTheConversation(): void
    {
        $caso = $this->aClaim();

        $this->reply($caso['reclamante']['token'], $caso['claimId'], 'Quiero añadir algo');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('THREAD_NOT_OPEN', $this->payload()['code']);
    }

    /**
     * Y solo puede escribir en **su** hilo: abrir el del reclamante no abre
     * el del reclamado.
     */
    public function testOpeningOneThreadDoesNotOpenTheOther(): void
    {
        $caso = $this->aClaim();

        $this->write($caso['moderador']['token'], $caso['claimId'], 'REPORTER', 'Solo a quien reclama');

        $this->reply($caso['reclamado']['token'], $caso['claimId'], 'Yo también quiero hablar');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('THREAD_NOT_OPEN', $this->payload()['code']);
    }

    /**
     * `RN-4`: la conversación **no revela la identidad del moderador**. Firma
     * como «Moderación», no con su nombre.
     *
     * Protege al moderador de represalias, y es lo que hace sostenible que la
     * decisión se discuta por su contenido y no por quién la tomó.
     */
    public function testTheModeratorIsNeverNamed(): void
    {
        $caso = $this->aClaim();

        $this->write($caso['moderador']['token'], $caso['claimId'], 'REPORTER', 'Una pregunta');

        $this->thread($caso['reclamante']['token'], $caso['claimId']);

        $cuerpo = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString($caso['moderador']['userId'], $cuerpo);
        self::assertStringNotContainsString($caso['reclamado']['userId'], $cuerpo, 'Ni la de la otra parte.');
    }

    /**
     * `RN-6`: el hilo se cierra al resolverse. **Se puede leer, no
     * continuar**: seguir escribiendo en un expediente cerrado sería alegar
     * ante quien ya decidió.
     */
    public function testWhenTheClaimIsResolvedTheThreadBecomesReadOnly(): void
    {
        $caso = $this->aClaim();

        $this->write($caso['moderador']['token'], $caso['claimId'], 'REPORTER', 'Una pregunta');
        $this->reply($caso['reclamante']['token'], $caso['claimId'], 'Una respuesta');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->review($caso['moderador']['token'], $caso['claimId'], 'REJECTED', 'No se aprecia infracción');
        self::assertResponseIsSuccessful();
        $this->consumeEverything();

        $this->thread($caso['reclamante']['token'], $caso['claimId']);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->payload()['messages'], 'Se sigue leyendo.');

        $this->reply($caso['reclamante']['token'], $caso['claimId'], 'Y una cosa más');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CLAIM_ALREADY_RESOLVED', $this->payload()['code']);
    }

    public function testTheModeratorCannotWriteOnAResolvedClaimEither(): void
    {
        $caso = $this->aClaim();

        $this->review($caso['moderador']['token'], $caso['claimId'], 'REJECTED', 'No se aprecia infracción');
        $this->consumeEverything();

        $this->write($caso['moderador']['token'], $caso['claimId'], 'REPORTER', 'Una cosa más');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CLAIM_ALREADY_RESOLVED', $this->payload()['code']);
    }

    /**
     * Hay reclamaciones que **no señalan a nadie**: un capítulo vive en otro
     * contexto y todavía no hay contrato que diga de quién es. Entonces no
     * hay con quién abrir el segundo hilo, y decirlo por su nombre es mejor
     * que abrir un hilo que nadie va a leer.
     */
    public function testAClaimWithoutASubjectHasNoSecondThread(): void
    {
        $moderador = $this->moderator('mod');
        $reclamante = $this->activatedPerson('reclamante');

        // Un capítulo vive en otro contexto y todavía no hay contrato que
        // diga de quién es, así que la reclamación se registra sin señalar a
        // nadie. El moderador lo averigua al abrirla.
        $claimId = $this->claim($reclamante['token'], 'CHAPTER', '01999999-9999-7999-8999-999999999999');

        $this->write($moderador['token'], $claimId, 'SUBJECT', 'A quien sea');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('CLAIM_HAS_NO_SUBJECT', $this->payload()['code']);
    }

    public function testAnEmptyMessageIsRefused(): void
    {
        $caso = $this->aClaim();

        $this->write($caso['moderador']['token'], $caso['claimId'], 'REPORTER', '   ');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('EMPTY_MESSAGE', $this->payload()['code']);
    }

    public function testOnlyModerationWritesFromTheAdminSide(): void
    {
        $caso = $this->aClaim();

        $this->write($caso['reclamante']['token'], $caso['claimId'], 'SUBJECT', 'Déjame escribirle');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * `RN-8`: todo mensaje queda en el registro, **con el hilo y sin el
     * cuerpo**. Lo que hay que poder revisar después es que el moderador
     * habló con una parte, no reproducir el expediente en un segundo sitio.
     */
    public function testEveryMessageLeavesItsTraceWithoutCopyingTheFile(): void
    {
        $admin = $this->moderator('admin', ModeratorLevel::ADMIN);
        $reclamante = $this->activatedPerson('reclamante');
        $reclamado = $this->activatedPerson('reclamado');

        $claimId = $this->claim($reclamante['token'], 'USER', $reclamado['userId']);
        $this->write($admin['token'], $claimId, 'REPORTER', 'Una pregunta reveladora');

        $this->client->request('GET', '/api/v1/admin/audit-log?action=CLAIM_MESSAGE_SENT', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$admin['token'],
        ]);
        self::assertResponseIsSuccessful();

        $entrada = $this->payload()['entries'][0];
        self::assertSame($admin['userId'], $entrada['actorId']);
        self::assertSame($claimId, $entrada['targetId']);
        self::assertStringNotContainsString(
            'Una pregunta reveladora',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    /**
     * @return array{claimId: string, moderador: array{token: string, userId: string}, reclamante: array{token: string, userId: string}, reclamado: array{token: string, userId: string}}
     */
    private function aClaim(): array
    {
        $moderador = $this->moderator('mod');
        $reclamante = $this->activatedPerson('reclamante');
        $reclamado = $this->activatedPerson('reclamado');

        return [
            'claimId' => $this->claim($reclamante['token'], 'USER', $reclamado['userId']),
            'moderador' => $moderador,
            'reclamante' => $reclamante,
            'reclamado' => $reclamado,
        ];
    }

    private function claim(string $token, string $targetType, string $targetId): string
    {
        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'reason' => 'OFFENSIVE',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['claimId'];
    }

    private function write(string $token, string $claimId, string $party, string $body): void
    {
        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/messages', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['party' => $party, 'body' => $body], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function reply(string $token, string $claimId, string $body): void
    {
        $this->client->request('POST', \sprintf('/api/v1/me/claims/%s/messages', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['body' => $body], \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function thread(string $token, string $claimId): void
    {
        $this->client->request('GET', \sprintf('/api/v1/me/claims/%s/messages', $claimId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function review(string $token, string $claimId, string $decision, string $motivation): void
    {
        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['decision' => $decision, 'motivation' => $motivation], \JSON_THROW_ON_ERROR));

        $this->capture();
    }
}
