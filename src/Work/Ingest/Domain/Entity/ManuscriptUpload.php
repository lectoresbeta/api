<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Domain\Entity;

use LectoresBeta\Work\Ingest\Domain\Exception\UploadNotFound;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ManuscriptUploadId;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ProposedChapter;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * Un manuscrito subido y todavía sin confirmar (`FEAT-WRK-002`).
 *
 * Existe porque el troceado se **propone** y el autor lo revisa (`W-4`): entre
 * la subida y la confirmación hay un rato, y en ese rato el texto tiene que
 * estar en algún sitio.
 *
 * **El fichero original no se guarda** (decidido con `FEAT-WRK-002`). Se
 * extrae el texto y el fichero se descarta: duplicar el almacenamiento de
 * obra inédita, y con él su superficie de exposición, a cambio de un valor
 * probatorio que hoy nadie usa no sale a cuenta. El día que el registro de
 * autoría (`FEAT-WRK-009`) se desbloquee podrá pedir lo que necesite.
 *
 * **Caduca**, y eso es media funcionalidad: una subida abandonada es una
 * novela entera ocupando sitio para siempre. La otra media es que al
 * confirmar la fila se borra — el texto ya vive en sus capítulos, y tenerlo
 * dos veces es tenerlo mal una de las dos.
 */
class ManuscriptUpload
{
    /**
     * Cuánto vive una subida sin confirmar.
     *
     * Suficiente para revisar treinta capítulos con calma y volver después de
     * comer; no tanto como para que una novela abandonada se quede de por
     * vida.
     */
    public const LIFETIME = '+24 hours';

    private string $id;

    private string $authorId;

    private string $filename;

    /**
     * Los capítulos propuestos, en JSON.
     *
     * Es una **estructura de paso**, no un modelo: vive horas, la lee una
     * sola clase y desaparece al confirmar. Darle tabla propia y filas por
     * capítulo sería montar un modelo paralelo al de `Chapter` para algo que
     * no llega a mañana.
     *
     * @var list<array{title: string|null, paragraphs: list<string>}>
     */
    private array $chapters;

    private \DateTimeImmutable $uploadedAt;

    private \DateTimeImmutable $expiresAt;

    /**
     * @param list<ProposedChapter> $chapters
     */
    public function __construct(
        ManuscriptUploadId $id,
        AuthorId $authorId,
        string $filename,
        array $chapters,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->authorId = $authorId->value();
        $this->filename = $filename;
        $this->chapters = array_map(
            static fn (ProposedChapter $chapter): array => [
                'title' => $chapter->title,
                'paragraphs' => $chapter->paragraphs,
            ],
            $chapters,
        );
        $this->uploadedAt = $now;
        $this->expiresAt = $now->modify(self::LIFETIME);
    }

    public function id(): ManuscriptUploadId
    {
        return ManuscriptUploadId::fromString($this->id);
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function uploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * @return list<ProposedChapter>
     */
    public function chapters(): array
    {
        return array_map(
            static fn (array $chapter): ProposedChapter => new ProposedChapter(
                $chapter['title'],
                $chapter['paragraphs'],
            ),
            $this->chapters,
        );
    }

    /**
     * La subida de otra persona y una caducada responden **igual que una que
     * no existe**: que exista es información sobre lo que alguien escribe.
     */
    public function usableBy(AuthorId $authorId, \DateTimeImmutable $now): void
    {
        if ($this->authorId !== $authorId->value() || $now > $this->expiresAt) {
            throw UploadNotFound::create();
        }
    }
}
