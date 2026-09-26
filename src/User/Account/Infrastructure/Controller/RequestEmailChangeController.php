<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Command\RequestEmailChange;
use LectoresBeta\User\Account\Application\Handler\RequestEmailChangeHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/me/email-change` (`FEAT-USR-040`).
 *
 * `202`: lo que ocurre a continuación es un correo, no una respuesta. Y la
 * dirección **todavía no ha cambiado** — sigue valiendo la anterior hasta que
 * alguien abra el enlace que acaba de salir hacia la nueva.
 */
#[AsController]
final readonly class RequestEmailChangeController
{
    public function __construct(
        private RequestEmailChangeHandler $requestChange,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        ($this->requestChange)(new RequestEmailChange(
            $user->getUserIdentifier(),
            (string) $body->string('email'),
            $body->string('currentPassword'),
        ));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
