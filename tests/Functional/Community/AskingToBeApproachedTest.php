<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las dos publicaciones que piden que te aborden (`FEAT-COM-004`,
 * `FEAT-COM-005`).
 *
 * Son hermanas de `FEAT-COM-003` y la diferencia está en qué imponen. Aquella
 * impone **un adjunto** —sin obra, «busco lectores» no se puede atender—;
 * estas dos imponen **una coherencia**: no se pide en el muro lo que se tiene
 * cerrado en los ajustes.
 *
 * Sin esa regla, publicar «busco writing buddy» con las propuestas cerradas
 * manda contra un muro a todo el que responda, y quien publicó no se entera
 * nunca de por qué no le escribe nadie. El fallo es silencioso por los dos
 * lados, que es la clase de fallo que no se descubre.
 */
final class AskingToBeApproachedTest extends EconomyScenario
{
    /**
     * `FEAT-COM-004` `RN-1`: con la puerta abierta —que es como nace toda
     * cuenta— sale sin pedir nada más.
     */
    public function testLookingForAWritingBuddyGoesOutByDefault(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->publish($autora['token'], 'LOOKING_FOR_WRITING_BUDDY', 'Busco alguien con quien escribir');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `FEAT-COM-005` `RN-1`: igual por el otro lado. Ofrecerse como lector
     * beta **no exige obra**: no se ofrece un texto, se ofrece uno mismo.
     */
    public function testOfferingYourselfAsABetaReaderGoesOutByDefault(): void
    {
        $lectora = $this->activatedPerson('lectora');

        $this->publish($lectora['token'], 'OFFERING_AS_BETA_READER', 'Leo fantasía y contesto rápido');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `FEAT-COM-004` `RN-2`: con las propuestas de writing buddy cerradas, se
     * rechaza diciendo cuál es la puerta.
     */
    public function testLookingForAWritingBuddyWithThatDoorShutIsRefused(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->reception($autora['token'], betaReaderInvitations: true, writingBuddyProposals: false);

        $this->publish($autora['token'], 'LOOKING_FOR_WRITING_BUDDY', 'Busco alguien con quien escribir');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('RECEPTION_CLOSED', $this->payload()['code']);
    }

    /**
     * `FEAT-COM-005` `RN-2`: y con las invitaciones de lector beta cerradas.
     */
    public function testOfferingYourselfWithThatDoorShutIsRefused(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $this->reception($lectora['token'], betaReaderInvitations: false, writingBuddyProposals: true);

        $this->publish($lectora['token'], 'OFFERING_AS_BETA_READER', 'Leo fantasía');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('RECEPTION_CLOSED', $this->payload()['code']);
    }

    /**
     * **Cada intención mira su propia puerta**, y esta es la prueba que lo
     * demuestra: con una cerrada y la otra abierta, una publicación sale y la
     * otra no.
     *
     * Sin esto, un solo booleano mal leído pasaría desapercibido en los dos
     * casos anteriores.
     */
    public function testEachIntentionLooksAtItsOwnDoor(): void
    {
        $quien = $this->activatedPerson('quien');
        $this->reception($quien['token'], betaReaderInvitations: false, writingBuddyProposals: true);

        $this->publish($quien['token'], 'LOOKING_FOR_WRITING_BUDDY', 'Busco compañía para escribir');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->publish($quien['token'], 'OFFERING_AS_BETA_READER', 'Y me ofrezco a leer');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    /**
     * `RN-3`: las otras dos intenciones **no comprueban ninguna puerta**.
     *
     * `GENERAL` no pide que te aborden, y `LOOKING_FOR_BETA_READERS` pide que
     * te lean, que es una solicitud de acceso y no una propuesta: tiene sus
     * propias reglas en `FEAT-COM-003`.
     */
    public function testTheOtherTwoIntentionsIgnoreTheSettings(): void
    {
        $autora = $this->activatedPerson('autora');
        $this->reception($autora['token'], betaReaderInvitations: false, writingBuddyProposals: false);

        $this->publish($autora['token'], 'GENERAL', 'Hoy he escrito mil palabras');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $workId = $this->createWork($autora['token'], 'La ciudad de los pájaros');
        $this->addChapter($workId, $autora['token'], words: 900);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        $this->publish($autora['token'], 'LOOKING_FOR_BETA_READERS', 'Busco quien lo lea', $workId);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `RN-4`: se comprueba **al publicar y solo al publicar**. Cerrar la
     * recepción después no retira lo publicado.
     *
     * Una publicación es un acto con fecha y un ajuste cambia cuando quiera
     * su dueño; hacer desaparecer textos viejos al tocar un interruptor
     * sorprendería a cualquiera.
     */
    public function testShuttingTheDoorLaterDoesNotWithdrawThePost(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->publish($autora['token'], 'LOOKING_FOR_WRITING_BUDDY', 'Busco alguien con quien escribir');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $postId = (string) $this->payload()['postId'];

        $this->reception($autora['token'], betaReaderInvitations: true, writingBuddyProposals: false);

        $this->client->request('GET', '/api/v1/posts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful();

        $ids = array_map(
            static fn (array $post): string => (string) $post['postId'],
            $this->payload()['posts'],
        );

        self::assertContains($postId, $ids);
    }

    /**
     * Una publicación de estas **puede llevar una obra**, como cualquier
     * otra que no sea reclutar lectores (`FEAT-COM-003` `RN-5`).
     *
     * Enseñar lo que escribes mientras buscas con quién escribir es
     * exactamente lo que alguien haría.
     */
    public function testItCanStillCarryAWork(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'Lo que ando escribiendo');
        $this->addChapter($workId, $autora['token'], words: 900);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        $this->publish($autora['token'], 'LOOKING_FOR_WRITING_BUDDY', 'Esto es lo mío', $workId);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    private function publish(string $token, string $type, string $body, ?string $workId = null): void
    {
        $this->client->request('POST', '/api/v1/posts', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(array_filter([
            'body' => $body,
            'type' => $type,
            'workId' => $workId,
        ]), \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function reception(string $token, bool $betaReaderInvitations, bool $writingBuddyProposals): void
    {
        $this->putAs('/api/v1/me/reception-settings', $token, [
            'betaReaderInvitations' => $betaReaderInvitations,
            'writingBuddyProposals' => $writingBuddyProposals,
        ]);
    }
}
