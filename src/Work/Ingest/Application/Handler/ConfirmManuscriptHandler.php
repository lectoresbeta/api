<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Port\ContentSanitiser;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Event\ChapterContentUpdated;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\Service\WordCounter;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Ingest\Application\Command\ConfirmManuscript;
use LectoresBeta\Work\Ingest\Domain\Exception\UploadNotFound;
use LectoresBeta\Work\Ingest\Domain\Repository\ManuscriptUploadRepository;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ManuscriptUploadId;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ProposedChapter;
use LectoresBeta\Work\Manuscript\Application\Service\DeclareGenres;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkCreated;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkTitle;

/**
 * Confirmar el troceado y crear la obra (`FEAT-WRK-002`).
 *
 * La segunda mitad. Lo que sale de aquí es **exactamente lo que sale de
 * `FEAT-WRK-001`**: una `Work` con sus `Chapter`, sus temáticas y sus hechos
 * publicados. Un manuscrito subido no es una obra de segunda clase, y si
 * saliera por otro camino acabaría teniendo otras reglas.
 *
 * **El texto pasa por el mismo saneador que el editor.** Un `.docx` es,
 * literalmente, lo mismo que se pega desde Word en el editor: no merece más
 * confianza por venir en un fichero.
 *
 * El autor puede **corregir los títulos** que se le propusieron, y nada más.
 * Reordenar o partir capítulos desde aquí sería reimplementar el editor
 * dentro de la ingesta; lo que se quiera cambiar después se cambia con
 * `FEAT-WRK-003` y `FEAT-WRK-005`, que ya existen.
 *
 * **La subida se borra al confirmar.** El texto ya vive en sus capítulos, y
 * tenerlo dos veces es tenerlo mal una de las dos.
 */
final readonly class ConfirmManuscriptHandler
{
    public function __construct(
        private ManuscriptUploadRepository $uploads,
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private DeclareGenres $genres,
        private ContentSanitiser $sanitiser,
        private WordCounter $words,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ConfirmManuscript $command): string
    {
        try {
            $upload = $this->uploads->ofId(ManuscriptUploadId::fromString($command->uploadId));
            $authorId = AuthorId::fromString($command->authorId);
        } catch (InvalidValue) {
            throw UploadNotFound::create();
        }

        if (null === $upload) {
            throw UploadNotFound::create();
        }

        $now = $this->clock->now();
        $upload->usableBy($authorId, $now);

        $work = new Work(WorkId::generate(), $authorId, $this->titleOf($command, $upload->filename()), $now);

        if (null !== $command->synopsis) {
            $work->describe($command->synopsis, $now);
        }

        $chapters = [];
        $position = 0;
        $words = 0;

        foreach ($upload->chapters() as $index => $proposed) {
            $content = $this->sanitiser->sanitise(self::html($proposed));

            // Un capítulo que se queda en nada tras sanear no se crea. Pasa
            // con una página que solo llevaba una imagen, y una obra con
            // capítulos vacíos dentro es peor que una con uno menos.
            if ('' === $content->text) {
                continue;
            }

            $chapter = new Chapter(
                ChapterId::generate(),
                $work->id(),
                ++$position,
                $now,
                self::titleAt($command, $index, $proposed),
            );
            $chapter->replaceContent($content, $this->words, $now);

            $chapters[] = $chapter;
            $words += $chapter->wordCount();
        }

        // Un fichero del que no sobrevive ni un capítulo no es una obra. Se
        // responde como una subida que no está: el autor vuelve a empezar.
        if ([] === $chapters) {
            throw UploadNotFound::create();
        }

        $work->recountContent($words, \count($chapters), $now);

        $this->session->execute(function () use ($work, $chapters, $command, $upload): void {
            $this->works->save($work);

            foreach ($chapters as $chapter) {
                $this->chapters->save($chapter);
            }

            $this->genres->on($work->id(), $command->genres);
            $this->uploads->remove($upload);
        });

        $this->events->publish(new WorkCreated(
            EventId::generate(),
            $work->id(),
            $work->authorId(),
            $work->title()->value(),
            $work->accessMode()->value,
            $now,
        ));

        // Un hecho por capítulo, igual que al añadirlos de uno en uno: lo que
        // cuesta corregir se decide en `Credits` a partir de estos, y una
        // ingesta que no los publicara dejaría la obra sin precio.
        foreach ($chapters as $chapter) {
            $this->events->publish(new ChapterContentUpdated(
                EventId::generate(),
                $chapter->id(),
                $work->id(),
                $work->authorId(),
                $chapter->position(),
                $chapter->wordCount(),
                $now,
            ));
        }

        return $work->id()->value();
    }

    /**
     * El título de la obra: el que mande el autor, y si no, el nombre del
     * fichero sin extensión. Nadie quiere una obra llamada «documento (3)»,
     * pero es mejor que rechazar la subida por un campo que se puede cambiar
     * después.
     */
    private function titleOf(ConfirmManuscript $command, string $filename): WorkTitle
    {
        $given = trim($command->title ?? '');

        if ('' !== $given) {
            return WorkTitle::fromString($given);
        }

        $stem = pathinfo($filename, \PATHINFO_FILENAME);

        return WorkTitle::fromString('' === trim($stem) ? 'Sin título' : $stem);
    }

    private static function titleAt(ConfirmManuscript $command, int $index, ProposedChapter $proposed): ?string
    {
        $given = trim($command->chapterTitles[$index] ?? '');

        return '' !== $given ? $given : $proposed->title;
    }

    /**
     * Los párrafos, como párrafos.
     *
     * Se escapan antes de envolverlos: el texto sale de un fichero ajeno, y
     * aunque el saneador vaya detrás, construir HTML concatenando texto sin
     * escapar es la forma de que un día el orden de las dos cosas cambie y
     * nadie se entere.
     */
    private static function html(ProposedChapter $chapter): string
    {
        $paragraphs = array_map(
            static fn (string $text): string => '<p>'.htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'</p>',
            array_values(array_filter($chapter->paragraphs, static fn (string $t): bool => '' !== trim($t))),
        );

        return implode('', $paragraphs);
    }
}
