<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Controller;

use LectoresBeta\Community\Post\Application\Handler\GetPostShareCardHandler;
use LectoresBeta\Shared\Infrastructure\Http\Sharing\ShareCardBody;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/posts/{postId}/share` (`FEAT-COM-020`).
 *
 * Igual que el de una obra, y con la misma regla que lo sostiene: **solo lo
 * que ya era público**. Una publicación para seguidores no tiene tarjeta,
 * porque una tarjeta la pide un rastreador sin sesión y acaba en la caché de
 * una red social.
 */
#[AsController]
final readonly class GetPostShareCardController
{
    public function __construct(private GetPostShareCardHandler $card)
    {
    }

    public function __invoke(string $postId): Response
    {
        return ShareCardBody::respond(($this->card)($postId));
    }
}
