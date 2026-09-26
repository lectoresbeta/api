<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Command\ResetPassword;
use LectoresBeta\User\Account\Application\Handler\ResetPasswordHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/auth/password/reset` (`FEAT-USR-007`).
 *
 * **No devuelve una sesión**, y es deliberado: sería cómodo y convertiría un
 * enlace de correo en un inicio de sesión completo, de forma que quien lo
 * interceptase entraría sin escribir nada. Después se entra por el login,
 * que es donde están sus límites y sus avisos.
 *
 * Tampoco devuelve nada del usuario. Una respuesta que acaba en un log no
 * necesita más superficie de la imprescindible.
 */
#[AsController]
final readonly class ResetPasswordController
{
    public function __construct(private ResetPasswordHandler $reset)
    {
    }

    public function __invoke(Request $request): Response
    {
        $body = JsonBody::of($request);

        ($this->reset)(new ResetPassword(
            (string) $body->string('token'),
            (string) $body->string('password'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
