<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Authentication\Application\Command\RenewSession;
use LectoresBeta\User\Authentication\Application\Handler\RenewSessionHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/auth/refresh` (`FEAT-USR-004`).
 *
 * Public: the refresh token **is** the authorisation. Requiring a valid
 * access token as well would defeat the point, since renewing is what happens
 * once the access token has expired.
 */
#[AsController]
final readonly class RenewSessionController
{
    public function __construct(private RenewSessionHandler $renew)
    {
    }

    public function __invoke(Request $request): Response
    {
        $session = ($this->renew)(new RenewSession(
            (string) JsonBody::of($request)->string('refreshToken'),
            $request->headers->get('User-Agent'),
        ));

        return new JsonResponse([
            'accessToken' => $session->accessToken,
            'refreshToken' => $session->refreshToken,
            'expiresIn' => $session->expiresIn,
        ]);
    }
}
