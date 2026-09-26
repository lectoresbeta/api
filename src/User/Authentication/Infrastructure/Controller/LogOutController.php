<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Authentication\Application\Command\LogOut;
use LectoresBeta\User\Authentication\Application\Handler\LogOutHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/auth/logout` (`FEAT-USR-004` `RN-12`).
 *
 * Always `204`, even for a token that never existed. Refusing would tell the
 * caller whether a token was real, and the outcome they asked for — being
 * logged out — is true either way.
 *
 * What it cannot do is invalidate the access token, which keeps working for
 * up to fifteen minutes (`decision:0007`).
 */
#[AsController]
final readonly class LogOutController
{
    public function __construct(private LogOutHandler $logOut)
    {
    }

    public function __invoke(Request $request): Response
    {
        ($this->logOut)(new LogOut((string) JsonBody::of($request)->string('refreshToken')));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
