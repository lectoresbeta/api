<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\Sharing\ShareCardBody;
use LectoresBeta\User\Profile\Application\Handler\GetProfileShareCardHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/users/{userId}/share` (`FEAT-USR-032`).
 *
 * Los metadatos con los que se pinta la previsualización cuando alguien pega
 * el enlace de un perfil en una red social.
 *
 * **Público**, y tiene que serlo: quien lo pide es un rastreador sin sesión.
 * Por eso responde solo por perfiles visibles para cualquiera, y por eso se
 * cachea en público.
 */
#[AsController]
final readonly class GetProfileShareCardController
{
    public function __construct(private GetProfileShareCardHandler $card)
    {
    }

    public function __invoke(string $userId): Response
    {
        return ShareCardBody::respond(($this->card)($userId));
    }
}
