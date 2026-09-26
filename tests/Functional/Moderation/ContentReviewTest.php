<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Moderation;

use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\ContentReview\Application\Handler\ReviewChapterContent;
use LectoresBeta\Moderation\ContentReview\Application\Port\ContentReviewer;
use LectoresBeta\Moderation\ContentReview\Domain\Enum\ContentReviewOutcome;
use LectoresBeta\Moderation\ContentReview\Domain\Repository\ContentReviewRepository;
use LectoresBeta\Moderation\ContentReview\Domain\ValueObject\ReviewVerdict;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterTexts;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use Symfony\Component\HttpFoundation\Response;

/**
 * La revisión automática de contenido (`FEAT-MOD-011`).
 *
 * **Hoy el revisor aprueba todo, y por eso lo que esta prueba defiende no es
 * lo que encuentra sino que el hueco esté abierto**: que el paso exista, que
 * el veredicto quede registrado con la versión que lo emitió, y que
 * sustituirlo no obligue a tocar `Work` ni el flujo de publicación.
 *
 * Lo segundo se comprueba literalmente: la mitad de los casos sustituye el
 * revisor por uno que marca, y todo lo demás sigue igual.
 */
final class ContentReviewTest extends EconomyScenario
{
    /**
     * `RN-2` y `RN-3`: aprueba todo, y queda anotado con la versión.
     */
    public function testEveryTextIsReviewedAndItsVerdictRecorded(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La obra revisada');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 300);

        $this->consumeEverything();

        $veredictos = $this->reviewsOf($chapterId);

        self::assertCount(1, $veredictos, 'Crear un capítulo es un texto nuevo que revisar.');
        self::assertSame(ContentReviewOutcome::PASSED, $veredictos[0]->outcome());
    }

    /**
     * `RN-8`: **al publicar y en cada edición**. Revisar solo al publicar
     * dejaría abierto el esquive obvio —publicar algo inocuo y editarlo
     * después— que vaciaría de sentido cualquier revisión futura.
     *
     * `RN-9`: se revisa **lo que cambia**. Editar un capítulo no vuelve a
     * revisar los otros.
     */
    public function testEachEditIsReviewedAgainAndOnlyWhatChanged(): void
    {
        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La obra revisada');
        $primero = $this->addChapter($workId, $autora['token'], words: 300);
        $segundo = $this->addChapter($workId, $autora['token'], words: 300, marker: 'Segundo');
        $this->consumeEverything();

        self::assertCount(1, $this->reviewsOf($primero));
        self::assertCount(1, $this->reviewsOf($segundo));

        $this->putAs(\sprintf('/api/v1/chapters/%s', $primero), $autora['token'], [
            'contentHtml' => '<p>'.implode(' ', array_fill(0, 320, 'reescrito')).'</p>',
        ]);
        self::assertResponseIsSuccessful();
        $this->capture();
        $this->consumeEverything();

        self::assertCount(2, $this->reviewsOf($primero), 'La edición se revisa otra vez.');
        self::assertCount(1, $this->reviewsOf($segundo), 'Y el que no cambió, no.');
    }

    /**
     * `RN-4` y `RN-5`, el camino completo con un revisor que **sí** marca:
     * el capítulo se retira y se abre una reclamación con reclamante
     * `SYSTEM`, y **no se sanciona a nadie**.
     *
     * Es la prueba que dice que sustituir la implementación basta: aquí no se
     * ha tocado `Work`, ni el flujo de publicación, ni los estados.
     */
    public function testAFlaggedChapterIsWithdrawnAndOpensASystemClaim(): void
    {
        $this->useReviewerThatFlags('Lenguaje de odio.');

        $autora = $this->activatedPerson('autora');
        $moderador = $this->moderator('moderadora');
        $workId = $this->createWork($autora['token'], 'La obra marcada');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 300);

        $this->consumeEverything();

        $veredictos = $this->reviewsOf($chapterId);
        self::assertCount(1, $veredictos);
        self::assertSame(ContentReviewOutcome::FLAGGED, $veredictos[0]->outcome());

        // `RN-4`: el capítulo queda retirado por el mismo camino que una
        // reclamación estimada. Su autora lo sigue viendo —bloquear no borra
        // nada— y nadie más lo alcanza.
        $extranya = $this->activatedPerson('extranya');
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$extranya['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        self::assertTrue(
            $this->chapterIsBlocked($chapterId),
            'Se retira por el bloqueo de moderación, que solo levanta un humano.',
        );

        // Y hay una reclamación esperando a que la mire un humano.
        $this->client->request('GET', '/api/v1/admin/claims', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$moderador['token'],
        ]);
        self::assertResponseIsSuccessful();
        $reclamaciones = $this->payload()['claims'];
        self::assertCount(1, $reclamaciones);
        self::assertSame('CHAPTER', $reclamaciones[0]['targetType']);
        self::assertSame($chapterId, $reclamaciones[0]['targetId']);

        // `RN-5`: marca, no sanciona. La cuenta de la autora sigue intacta.
        $this->sheet($moderador['token'], $autora['userId']);
        self::assertSame([], $this->payload()['sanctions'], 'Ningún veredicto automático sanciona solo.');
        self::assertSame('ACTIVE', $this->payload()['account']['status']);
    }

    /**
     * `RN-6`, la decisión contraria a la intuitiva: **si el revisor falla, el
     * texto se publica igual**. Bloquear ante una caída sería impedir
     * publicar por nada.
     *
     * El fallo queda anotado como un veredicto aparte, que es lo que
     * permitirá revisar esos textos cuando el revisor vuelva.
     */
    public function testAReviewerThatBlowsUpDoesNotStopAnybodyFromPublishing(): void
    {
        $this->useReviewerThatFails();

        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La obra revisada');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 300);

        $this->consumeEverything();

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseIsSuccessful('El texto está donde tiene que estar.');

        $veredictos = $this->reviewsOf($chapterId);
        self::assertCount(1, $veredictos);
        self::assertSame(ContentReviewOutcome::PASSED, $veredictos[0]->outcome(), 'Aprobado, y con nota.');
    }

    /**
     * `RN-7`: apagado no aprueba. **No revisa**, y no anota un veredicto que
     * nadie emitió.
     */
    public function testDisabledItReviewsNothingAtAll(): void
    {
        $this->disableReviewer();

        $autora = $this->activatedPerson('autora');
        $workId = $this->createWork($autora['token'], 'La obra sin revisar');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 300);

        $this->consumeEverything();

        self::assertSame([], $this->reviewsOf($chapterId));
    }

    private function useReviewerThatFlags(string $reason): void
    {
        self::getContainer()->set(ContentReviewer::class, new class($reason) implements ContentReviewer {
            public function __construct(private readonly string $reason)
            {
            }

            public function review(string $text): ReviewVerdict
            {
                return ReviewVerdict::flagged('TEST', '1', $this->reason);
            }
        });
    }

    private function useReviewerThatFails(): void
    {
        self::getContainer()->set(ContentReviewer::class, new class implements ContentReviewer {
            public function review(string $text): ReviewVerdict
            {
                throw new \RuntimeException('The reviewer is down.');
            }
        });
    }

    /**
     * El interruptor es un argumento del consumidor, así que apagarlo es
     * sustituir el consumidor por uno construido apagado. Se monta con las
     * mismas piezas que el de verdad: lo único que cambia es el `false`.
     */
    private function disableReviewer(): void
    {
        $container = self::getContainer();

        /** @var ContentReviewer $reviewer */
        $reviewer = $container->get(ContentReviewer::class);
        /** @var ContentReviewRepository $reviews */
        $reviews = $container->get(ContentReviewRepository::class);
        /** @var ClaimRepository $claims */
        $claims = $container->get(ClaimRepository::class);
        /** @var ChapterTexts $texts */
        $texts = $container->get(ChapterTexts::class);
        /** @var EventPublisher $events */
        $events = $container->get(EventPublisher::class);
        /** @var TransactionalSession $session */
        $session = $container->get(TransactionalSession::class);
        /** @var Clock $clock */
        $clock = $container->get(Clock::class);

        $container->set(ReviewChapterContent::class, new ReviewChapterContent(
            $reviewer,
            $reviews,
            $claims,
            $texts,
            $events,
            $session,
            $clock,
            enabled: false,
        ));
    }

    /**
     * @return list<\LectoresBeta\Moderation\ContentReview\Domain\Entity\ContentReview>
     */
    private function reviewsOf(string $chapterId): array
    {
        /** @var ContentReviewRepository $reviews */
        $reviews = self::getContainer()->get(ContentReviewRepository::class);

        return $reviews->historyOf(ClaimTargetType::CHAPTER, $chapterId);
    }

    private function chapterIsBlocked(string $chapterId): bool
    {
        /** @var ChapterRepository $chapters */
        $chapters = self::getContainer()->get(ChapterRepository::class);

        return true === $chapters->ofId(ChapterId::fromString($chapterId))?->isBlocked();
    }

    private function sheet(string $token, string $userId): void
    {
        $this->client->request('GET', \sprintf('/api/v1/admin/users/%s', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();
    }
}
