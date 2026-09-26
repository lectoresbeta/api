<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * La cola de reclamaciones (`FEAT-MOD-008`).
 *
 * **La prioridad es la antigüedad, y no se puede cambiar.** No hay parámetro
 * de ordenación y no lo hay a propósito: en una cola cuyo orden elige quien
 * la trabaja, los casos incómodos se hunden, y una reclamación sin resolver
 * es alguien esperando.
 *
 * Lo que sí se puede es **acotar**, por motivo y por clase de objeto. Es otra
 * cosa: elegir a qué dedicarse no es elegir qué atender antes. Dentro de lo
 * acotado sigue mandando quien lleva más tiempo esperando.
 *
 * Y el total, que es lo que convierte una página en una cola: sin él, quien
 * modera ve veinte expedientes y no sabe si detrás hay cero o mil.
 */
final class ClaimQueueTest extends EconomyScenario
{
    /**
     * `RN-1`: de la más antigua a la más reciente.
     */
    public function testTheQueueIsOldestFirst(): void
    {
        $moderadora = $this->moderator('moderadora');

        $primera = $this->aClaim('OFFENSIVE');
        $segunda = $this->aClaim('SPAM');
        $tercera = $this->aClaim('PLAGIARISM');

        self::assertSame([$primera, $segunda, $tercera], $this->queue($moderadora['token']));
    }

    /**
     * `RN-2`: acotar por motivo, que es el filtro que pidió la ficha. Son
     * doce motivos y no piden ni el mismo criterio ni, a veces, la misma
     * persona.
     */
    public function testFilteringByReason(): void
    {
        $moderadora = $this->moderator('moderadora');

        $ofensiva = $this->aClaim('OFFENSIVE');
        $spam = $this->aClaim('SPAM');

        $encontrado = $this->queue($moderadora['token'], '?reason=SPAM');

        self::assertSame([$spam], $encontrado);
        self::assertNotContains($ofensiva, $encontrado);
    }

    /**
     * `RN-3`: y por clase de objeto. Revisar textos y revisar conducta son
     * dos trabajos distintos.
     */
    public function testFilteringByTargetType(): void
    {
        $moderadora = $this->moderator('moderadora');
        $autora = $this->activatedPerson('autora');

        $sobreUnaObra = $this->aClaim('OFFENSIVE');
        $sobreAlguien = $this->claimAbout($this->activatedPerson('quien')['token'], 'USER', $autora['userId'], 'HARASSMENT');

        $encontrado = $this->queue($moderadora['token'], '?targetType=USER');

        self::assertSame([$sobreAlguien], $encontrado);
        self::assertNotContains($sobreUnaObra, $encontrado);
    }

    /**
     * **Acotar no reordena.** Dentro del filtro, sigue mandando la más
     * antigua.
     */
    public function testFilteringKeepsTheOldestFirstOrder(): void
    {
        $moderadora = $this->moderator('moderadora');

        $primeraSpam = $this->aClaim('SPAM');
        $this->aClaim('OFFENSIVE');
        $segundaSpam = $this->aClaim('SPAM');

        self::assertSame([$primeraSpam, $segundaSpam], $this->queue($moderadora['token'], '?reason=SPAM'));
    }

    /**
     * `RN-4`: el total, **con los mismos filtros que la lista**.
     *
     * Un total que contara otra cosa sería peor que no darlo, porque nadie lo
     * comprueba.
     */
    public function testTheTotalCountsTheSameThingAsTheList(): void
    {
        $moderadora = $this->moderator('moderadora');

        $this->aClaim('SPAM');
        $this->aClaim('SPAM');
        $this->aClaim('OFFENSIVE');

        $this->ask($moderadora['token'], '');
        self::assertSame(3, $this->payload()['total']);

        $this->ask($moderadora['token'], '?reason=SPAM');
        self::assertSame(2, $this->payload()['total']);
    }

    /**
     * Y **cuenta todo lo que hay, no lo que cabe en la página**: es
     * exactamente para eso.
     */
    public function testTheTotalLooksPastThePage(): void
    {
        $moderadora = $this->moderator('moderadora');

        for ($i = 0; $i < 3; ++$i) {
            $this->aClaim('SPAM');
        }

        $this->ask($moderadora['token'], '?limit=1');

        self::assertCount(1, $this->payload()['claims']);
        self::assertSame(3, $this->payload()['total']);
    }

    /**
     * `RN-5`: lo ya resuelto **sale de la cola**, y del total.
     */
    public function testResolvedClaimsLeaveTheQueueAndTheTotal(): void
    {
        $moderadora = $this->moderator('moderadora');

        $claimId = $this->aClaim('OFFENSIVE');
        $otra = $this->aClaim('SPAM');

        $this->client->request('POST', \sprintf('/api/v1/admin/claims/%s/review', $claimId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderadora['token'],
        ], content: json_encode([
            'decision' => 'REJECTED',
            'motivation' => 'No incumple ninguna norma de la plataforma.',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        $this->ask($moderadora['token'], '');

        self::assertSame([$otra], array_map(
            static fn (array $claim): string => (string) $claim['claimId'],
            $this->payload()['claims'],
        ));
        self::assertSame(1, $this->payload()['total']);
    }

    /**
     * `RN-6`: **el filtro no abre nada.** Una reclamación en la que quien
     * mira es parte no aparece, ni acotando por su motivo exacto.
     *
     * Es la exclusión de `FEAT-MOD-002` `RN-1`, y la prueba está aquí porque
     * un filtro escrito como una consulta aparte habría sido el sitio natural
     * donde perderla.
     */
    public function testFilteringDoesNotReachWhatTheQueueHides(): void
    {
        $moderadora = $this->moderator('moderadora');

        // Una reclamación presentada por la propia moderadora.
        $autora = $this->activatedPerson('autora');
        $workId = $this->aPublishedWork($autora);
        $suya = $this->claimAbout($moderadora['token'], 'WORK', $workId, 'SPAM');

        $ajena = $this->aClaim('SPAM');

        $encontrado = $this->queue($moderadora['token'], '?reason=SPAM');

        self::assertContains($ajena, $encontrado);
        self::assertNotContains($suya, $encontrado, 'Verla ya sería enterarse de a quién señala.');

        $this->ask($moderadora['token'], '?reason=SPAM');
        self::assertSame(1, $this->payload()['total'], 'Y tampoco la cuenta.');
    }

    /**
     * `RN-7`: un filtro que no es un valor del catálogo **se rechaza**.
     *
     * Devolverle la cola entera porque escribió mal un motivo le haría creer
     * que de ese motivo hay muchas más de las que hay.
     */
    public function testAnUnknownFilterIsRefused(): void
    {
        $moderadora = $this->moderator('moderadora');

        $this->ask($moderadora['token'], '?reason=NO_ME_GUSTA');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->ask($moderadora['token'], '?targetType=GALAXIA');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * La cola es del backoffice: sin el rol no se ve, y sin sesión tampoco.
     */
    public function testTheQueueIsForModeratorsOnly(): void
    {
        $cualquiera = $this->activatedPerson('cualquiera');

        $this->ask($cualquiera['token'], '');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', '/api/v1/admin/claims');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Una cola vacía responde una lista vacía y un cero, no un error.
     */
    public function testAnEmptyQueueIsAListAndAZero(): void
    {
        $moderadora = $this->moderator('moderadora');

        $this->ask($moderadora['token'], '');

        self::assertSame([], $this->payload()['claims']);
        self::assertSame(0, $this->payload()['total']);
    }

    /**
     * Una reclamación nueva sobre una obra recién publicada, presentada por
     * alguien distinto cada vez.
     */
    private function aClaim(string $reason): string
    {
        static $n = 0;

        $autora = $this->activatedPerson('autora'.$n);
        $quienReclama = $this->activatedPerson('reclamante'.$n);
        ++$n;

        return $this->claimAbout($quienReclama['token'], 'WORK', $this->aPublishedWork($autora), $reason);
    }

    /**
     * @param array{token: string, userId: string} $author
     */
    private function aPublishedWork(array $author): string
    {
        $workId = $this->createWork($author['token'], 'La obra reclamada');
        $this->addChapter($workId, $author['token'], words: 900);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $author['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        return $workId;
    }

    private function claimAbout(string $token, string $targetType, string $targetId, string $reason): string
    {
        $this->client->request('POST', '/api/v1/claims', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'reason' => $reason,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        return (string) $this->payload()['claimId'];
    }

    private function ask(string $token, string $query): void
    {
        $this->client->request('GET', '/api/v1/admin/claims'.$query, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @return list<string>
     */
    private function queue(string $token, string $query = ''): array
    {
        $this->ask($token, $query);
        self::assertResponseIsSuccessful();

        return array_values(array_map(
            static fn (array $claim): string => (string) $claim['claimId'],
            $this->payload()['claims'],
        ));
    }
}
