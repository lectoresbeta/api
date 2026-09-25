<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\DTO;

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
    ) {
    }
}
