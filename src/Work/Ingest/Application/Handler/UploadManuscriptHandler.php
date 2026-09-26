<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Domain\Service\WordCounter;
use LectoresBeta\Work\Ingest\Application\Command\UploadManuscript;
use LectoresBeta\Work\Ingest\Application\DTO\ProposedManuscript;
use LectoresBeta\Work\Ingest\Application\Port\DocumentTextExtractor;
use LectoresBeta\Work\Ingest\Domain\Entity\ManuscriptUpload;
use LectoresBeta\Work\Ingest\Domain\Exception\DocumentNotReadable;
use LectoresBeta\Work\Ingest\Domain\Repository\ManuscriptUploadRepository;
use LectoresBeta\Work\Ingest\Domain\Service\ChapterSplitter;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ManuscriptUploadId;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ProposedChapter;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * Subir un manuscrito y **proponer** cómo se trocea (`FEAT-WRK-002`, `W-4`).
 *
 * Es la primera mitad: aquí no se crea ninguna obra. El autor revisa la
 * propuesta y confirma en `ConfirmManuscriptHandler`, y hasta entonces lo
 * único que existe es una fila temporal que caduca sola.
 *
 * **El fichero original no se guarda.** Se extrae el texto y se descarta:
 * duplicar el almacenamiento de obra inédita, y con él su superficie de
 * exposición, a cambio de un valor probatorio que hoy nadie usa no sale a
 * cuenta.
 *
 * El tamaño se comprueba **antes de abrir nada**. Un zip pequeño puede
 * descomprimirse en gigabytes, así que el adaptador vuelve a mirar por
 * dentro; este límite es el de lo que llega por la red.
 */
final readonly class UploadManuscriptHandler
{
    /**
     * Diez megas.
     *
     * Una novela de setenta y cinco mil palabras en `.docx` ocupa uno o dos;
     * el resto del margen es para quien trae imágenes pegadas, que no se van
     * a usar pero vienen dentro del fichero.
     */
    public const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Cuánto se enseña de cada capítulo en la pantalla de revisión.
     */
    private const PREVIEW = 160;

    public function __construct(
        private DocumentTextExtractor $extractor,
        private ChapterSplitter $splitter,
        private WordCounter $words,
        private ManuscriptUploadRepository $uploads,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(UploadManuscript $command): ProposedManuscript
    {
        if ('' === $command->bytes) {
            throw DocumentNotReadable::withoutAnyText();
        }

        if (\strlen($command->bytes) > self::MAX_BYTES) {
            throw DocumentNotReadable::becauseItIsTooLarge(self::MAX_BYTES);
        }

        $chapters = $this->splitter->split($this->extractor->extract($command->bytes, $command->filename));

        $upload = new ManuscriptUpload(
            ManuscriptUploadId::generate(),
            AuthorId::fromString($command->authorId),
            self::safeName($command->filename),
            $chapters,
            $this->clock->now(),
        );

        $this->session->execute(function () use ($upload): void {
            $this->uploads->save($upload);
        });

        return $this->proposal($upload, $chapters);
    }

    /**
     * @param list<ProposedChapter> $chapters
     */
    private function proposal(ManuscriptUpload $upload, array $chapters): ProposedManuscript
    {
        $rows = [];
        $total = 0;

        foreach ($chapters as $position => $chapter) {
            $words = $this->words->count($chapter->text());
            $total += $words;

            $rows[] = [
                'position' => $position + 1,
                'title' => $chapter->title,
                'wordCount' => $words,
                'preview' => $chapter->preview(self::PREVIEW),
            ];
        }

        return new ProposedManuscript(
            $upload->id()->value(),
            $upload->filename(),
            $rows,
            $total,
            $upload->expiresAt(),
        );
    }

    /**
     * El nombre original se guarda **solo para que el autor reconozca su
     * subida**, y saneado: nunca se usa como ruta ni se sirve como fichero
     * (`file-uploads.md`).
     */
    private static function safeName(string $filename): string
    {
        $base = basename(str_replace('\\', '/', $filename));
        $clean = preg_replace('/[^\p{L}\p{N}._ -]+/u', '', $base) ?? '';

        return '' === trim($clean) ? 'manuscrito' : mb_substr(trim($clean), 0, 120);
    }
}
