<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\DTO;

/**
 * Una página del muro y dónde empieza la siguiente (`FEAT-COM-001` `RN-4`).
 *
 * Puede traer menos tarjetas que el límite pedido sin que eso signifique que
 * se acabó: el perfil de un autor que ha dejado de ser visible deja un hueco,
 * igual que en las listas de seguidores. **Lo que dice si hay más es
 * `nextCursor`**, nunca cuántas llegaron.
 *
 * Sin total. Contar aquí sería contar lo que ve cada persona, una cifra
 * distinta por visitante y una consulta cara en la pantalla que más se abre.
 */
final readonly class PostPage
{
    /**
     * @param list<PostCard> $posts
     */
    public function __construct(
        public array $posts,
        public ?string $nextCursor,
    ) {
    }
}
