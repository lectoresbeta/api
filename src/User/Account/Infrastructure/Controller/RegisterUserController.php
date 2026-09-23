<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Command\RegisterUser;
use LectoresBeta\User\Account\Application\Handler\RegisterUserHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/auth/register` (`FEAT-USR-001`).
 *
 * Always `202`, with an empty body, **whether or not that address already had
 * an account**. The temptation is to return `201` on success and something
 * else on a duplicate; that turns the sign-up form into a tool for finding
 * out who is on the platform, which `RN-14` forbids.
 *
 * For the same reason it does not return a session: a session only when the
 * address was free is the same oracle wearing a different hat. The client
 * still has the credentials the person just typed, so it can log in itself.
 */
#[AsController]
final readonly class RegisterUserController
{
    public function __construct(private RegisterUserHandler $register)
    {
    }

    public function __invoke(Request $request): Response
    {
        $body = JsonBody::of($request);
        $legal = $body->nested('acceptedLegalVersions');

        ($this->register)(new RegisterUser(
            (string) $body->string('email'),
            (string) $body->string('password'),
            $legal->string('termsOfUse'),
            $legal->string('privacyPolicy'),
            $body->string('invitationToken'),
            $request->getClientIp(),
        ));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
