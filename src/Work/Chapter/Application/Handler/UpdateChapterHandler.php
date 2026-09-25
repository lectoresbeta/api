<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Command\UpdateChapter;
use LectoresBeta\Work\Chapter\Application\Port\ContentSanitiser;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Entity\ChapterVersion;
use LectoresBeta\Work\Chapter\Domain\Event\ChapterContentUpdated;
use LectoresBeta\Work\Chapter\Domain\Exception\ChapterIsBlocked;
use LectoresBeta\Work\Chapter\Domain\Exception\EmptyChapter;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterVersionRepository;
use LectoresBeta\Work\Chapter\Domain\Service\WordCounter;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterVersionId;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * Editar un capítulo (`FEAT-WRK-005`).
 *
 * Aquí vive la única decisión difícil de la funcionalidad: **si el texto que
 * se va a pisar lo ha leído alguien**.
 *
 * - nadie ha empezado a corregirlo → se sobrescribe y la versión no cambia.
 *   Un autor que teclea y guarda veinte veces no deja veinte copias;
 * - alguien lo está corrigiendo o lo corrigió → **se archiva una copia** y el
 *   capítulo pasa a la siguiente versión. La corrección de esa persona sigue
 *   apuntando a la suya, y por eso su trabajo sigue teniendo sentido cuando
 *   el autor lo lee un mes después.
 *
 * El recuento de palabras **no se recibe, se calcula**: de él depende el
 * precio de cada corrección, y dejar que lo fije quien llama sería dejar que
 * lo fije quien paga.
 *
 * Y se publica `ChapterContentUpdated`, que es lo que lleva a `Credits` a
 * reprecio. Ese cálculo existía desde el primer día y no se había ejecutado
 * nunca, porque el texto no podía cambiar.
 */
final readonly class UpdateChapterHandler
{
    public function __construct(
        private ChapterRepository $chapters,
        private ChapterVersionRepository $versions,
        private WorkRepository $works,
        private ContentSanitiser $sanitiser,
        private WordCounter $words,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateChapter $command): void
    {
        [$chapter, $work] = $this->owned($command);

        // El contenido reclamado se conserva tal cual: es justo lo que puede
        // hacer falta si alguien discute la decisión (`FEAT-MOD-003` `RN-6`).
        if ($chapter->isBlocked()) {
            throw ChapterIsBlocked::create();
        }

        $content = $this->sanitiser->sanitise($command->contentHtml);

        if ('' === $content->text) {
            throw EmptyChapter::create();
        }

        $retitles = $command->titleWasSent && self::trimmed($command->title) !== $chapter->title();
        $rewrites = $content->html !== $chapter->content()->html;

        if (!$retitles && !$rewrites) {
            return;
        }

        $now = $this->clock->now();
        $archived = $chapter->needsArchivingBeforeEditing();
        $previousWords = $chapter->wordCount();
        $copy = $archived ? $this->copyOf($chapter, $now) : null;

        if ($retitles) {
            $chapter->retitle(self::trimmed($command->title), $now);
        }

        if ($rewrites) {
            $chapter->replaceContent($content, $this->words, $now);
        }

        if ($archived) {
            $chapter->openNextVersion($now);
        }

        $this->session->execute(function () use ($chapter, $work, $copy, $previousWords, $now): void {
            if (null !== $copy) {
                $this->versions->add($copy);
            }

            $this->chapters->save($chapter);

            // El total de la obra se ajusta por la diferencia y no se vuelve
            // a sumar entero: el capítulo que se acaba de tocar todavía no es
            // visible para una consulta.
            $work->recountContent(
                $this->chapters->wordCountOfWork($work->id()) - $previousWords + $chapter->wordCount(),
                $this->chapters->countOfWork($work->id()),
                $now,
            );
            $this->works->save($work);
        });

        if (!$rewrites) {
            return;
        }

        $this->events->publish(new ChapterContentUpdated(
            EventId::generate(),
            $chapter->id(),
            $work->id(),
            $work->authorId(),
            $chapter->position(),
            $chapter->wordCount(),
            $now,
            $chapter->version(),
        ));
    }

    private function copyOf(Chapter $chapter, \DateTimeImmutable $now): ChapterVersion
    {
        return new ChapterVersion(
            ChapterVersionId::generate(),
            $chapter->id(),
            $chapter->version(),
            $chapter->title(),
            $chapter->content(),
            $chapter->wordCount(),
            $now,
        );
    }

    /**
     * @return array{0: Chapter, 1: Work}
     */
    private function owned(UpdateChapter $command): array
    {
        try {
            $chapter = $this->chapters->ofId(ChapterId::fromString($command->chapterId));
            $authorId = AuthorId::fromString($command->authorId);
        } catch (InvalidValue) {
            throw WorkNotFound::create();
        }

        if (null === $chapter) {
            throw WorkNotFound::create();
        }

        $work = $this->works->ofId($chapter->workId());

        if (null === $work || !$work->authorId()->equals($authorId)) {
            throw WorkNotFound::create();
        }

        return [$chapter, $work];
    }

    private static function trimmed(?string $title): ?string
    {
        $text = trim((string) $title);

        return '' === $text ? null : $text;
    }
}
