<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Command\ResendActivationEmail;
use LectoresBeta\User\Account\Application\Handler\ResendActivationEmailHandler;
use LectoresBeta\User\Account\Infrastructure\Security\ActivationResendThrottle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/auth/activation/resend` (`FEAT-USR-021`).
 *
 * **Siempre `202`**, exista o no esa cuenta y esté o no activada (`RN-4`,
 * `RN-5`). No es una cortesía: la diferencia entre `202` y cualquier otra
 * cosa convertiría este formulario en un comprobador de qué direcciones
 * tienen cuenta en la plataforma.
 *
 * `202` y no `204` porque describe lo que de verdad ocurre: la petición queda
 * aceptada y el correo sale después, por la cola (`RN-6`). Esperar al envío
 * ataría la respuesta a un proveedor externo.
 *
 * Sin sesión, y a propósito (`R-1`): quien cierra el navegador antes de
 * activar no puede entrar, así que exigirla lo dejaría sin salida.
 */
#[AsController]
final readonly class ResendActivationEmailController
{
    public function __construct(
        private ResendActivationEmailHandler $resend,
        private ActivationResendThrottle $throttle,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $email = (string) JsonBody::of($request)->string('email');

        $this->throttle->check($email, $request->getClientIp() ?? 'unknown');

        ($this->resend)(new ResendActivationEmail($email));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
