<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\Sharing\ShareCardBody;
use LectoresBeta\Work\Manuscript\Application\Handler\GetWorkShareCardHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/works/{workId}/share` (`FEAT-WRK-011`).
 *
 * Los metadatos con los que se pinta la previsualización cuando alguien pega
 * el enlace de una obra en una red social.
 *
 * **Público**, y tiene que serlo: quien lo pide es un rastreador sin sesión.
 * Por eso responde solo por obras visibles para cualquiera, y por eso se
 * cachea en público.
 */
#[AsController]
final readonly class GetWorkShareCardController
{
    public function __construct(private GetWorkShareCardHandler $card)
    {
    }

    public function __invoke(string $workId): Response
    {
        return ShareCardBody::respond(($this->card)($workId));
    }
}
