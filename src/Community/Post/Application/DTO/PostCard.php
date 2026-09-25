<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\DTO;

use LectoresBeta\Community\Mention\Application\DTO\MentionView;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

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
    ) {
    }
}
