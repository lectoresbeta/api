<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\DTO;

use LectoresBeta\Community\Mention\Application\DTO\MentionView;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkCard;

/**
 * Una tarjeta del muro (`FEAT-COM-001` `RN-5`, `RN-6`).
 *
 * El autor viaja **resuelto** —nombre, `@usuario`, avatar— porque una tarjeta
 * con un identificador no se puede pintar, y pedirlos de uno en uno sería una
 * consulta por tarjeta.
 *
 * Los contadores vienen de la propia publicación y no de un `COUNT` por
 * tarjeta: un muro que cuenta comentarios en cada carga se degrada justo
 * cuando la plataforma empieza a funcionar.
 *
 * `edited` es un booleano y no una fecha. Lo que hace falta decirle a quien
 * lee un comentario es **que el texto de arriba cambió**; cuándo exactamente
 * no cambia nada y añadiría un dato más que mantener.
 */
final readonly class PostCard
{
    public function __construct(
        public string $postId,
        public DirectoryEntry $author,
        public string $body,
        public string $type,
        public string $format,
        public string $audience,
        public ?string $imageUrl,
        public ?string $linkUrl,
        public ?string $workId,
        public int $commentCount,
        public int $likeCount,
        public int $repostCount,
        /**
         * Si quien mira lo ha apoyado, para que el botón salga resaltado
         * (`FEAT-COM-008` `RN-7`). Va en la tarjeta y no se pregunta aparte:
         * una petición por corazón sería veinte por muro.
         */
        public bool $likedByViewer,
        public bool $edited,
        public \DateTimeImmutable $createdAt,
        /**
         * Quién lo ha vuelto a sacar, cuando la entrada es un repost.
         *
         * El resto de la tarjeta sigue siendo **del original**: su autor, su
         * texto, sus contadores. Un repost es una referencia, no una
         * publicación aparte, así que lo único que añade es la cabecera.
         */
        public ?DirectoryEntry $repostedBy = null,
        public ?string $repostComment = null,
        public ?\DateTimeImmutable $repostedAt = null,
        /**
         * Aparte del texto, y no incrustadas en él: que cliente y servidor
         * tengan que coincidir en cómo se parsea una cadena es una fuente
         * clásica de discrepancias, y aquí la discrepancia sería un enlace
         * apuntando a quien no es.
         *
         * @var list<MentionView>
         */
        public array $mentions = [],
        /**
         * La obra citada, **viva** (`FEAT-COM-028`).
         *
         * Nula cuando la publicación no cita ninguna, y también cuando la que
         * citaba ha dejado de ser visible —borrada, archivada o bloqueada por
         * un moderador—. La publicación se queda entera y la tarjeta
         * desaparece: el texto es de quien lo escribió, la obra es de quien la
         * escribió, y ninguno de los dos decide sobre lo del otro (`RN-4`).
         */
        public ?WorkCard $work = null,
    ) {
    }
}
