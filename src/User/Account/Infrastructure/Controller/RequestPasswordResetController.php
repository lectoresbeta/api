<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Command\RequestPasswordReset;
use LectoresBeta\User\Account\Application\Handler\RequestPasswordResetHandler;
use LectoresBeta\User\Account\Infrastructure\Security\PasswordResetThrottle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/auth/password/forgotten` (`FEAT-USR-007`).
 *
 * **Siempre `202`**, exista o no esa cuenta y esté bien escrito o no el
 * correo (`RN-1`, `RN-2`). Cualquier otra cosa convertiría este formulario en
 * un comprobador de qué direcciones tienen cuenta, que es justo la lista que
 * busca quien prepara un engaño.
 *
 * Sin sesión, porque no puede haberla: quien no recuerda su contraseña no
 * puede iniciarla.
 */
#[AsController]
final readonly class RequestPasswordResetController
{
    public function __construct(
        private RequestPasswordResetHandler $request,
        private PasswordResetThrottle $throttle,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $email = (string) JsonBody::of($request)->string('email');

        $this->throttle->check($email, $request->getClientIp() ?? 'unknown');

        ($this->request)(new RequestPasswordReset($email));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
