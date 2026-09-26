<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Command\ConfirmEmailChange;
use LectoresBeta\User\Account\Application\Handler\ConfirmEmailChangeHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/me/email-change/confirm` (`FEAT-USR-040`).
 *
 * **No exige sesión**, aunque cuelgue de `/me`: el enlace se abre donde está
 * abierto el buzón nuevo, que muchas veces es otro dispositivo. Lo que
 * autoriza es el token, de un solo uso y atado a esa solicitud.
 *
 * Confirmar **cierra las sesiones**, así que después hay que entrar otra vez
 * — ya con la dirección nueva.
 */
#[AsController]
final readonly class ConfirmEmailChangeController
{
    public function __construct(private ConfirmEmailChangeHandler $confirm)
    {
    }

    public function __invoke(Request $request): Response
    {
        ($this->confirm)(new ConfirmEmailChange(
            (string) JsonBody::of($request)->string('token'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
