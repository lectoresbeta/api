<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Command\ActivateAccount;
use LectoresBeta\User\Account\Application\Handler\ActivateAccountHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/auth/activate` (`FEAT-USR-020`).
 *
 * The token arrives **in the body and not in the path**, so it stays out of
 * server logs and browser history. The email links to a frontend page that
 * pulls it from the URL and posts it here.
 */
#[AsController]
final readonly class ActivateAccountController
{
    public function __construct(private ActivateAccountHandler $activate)
    {
    }

    public function __invoke(Request $request): Response
    {
        ($this->activate)(new ActivateAccount((string) JsonBody::of($request)->string('token')));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
