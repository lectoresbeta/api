<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\AuthorPage\Application\Command\UpdateAuthorPageStyle;
use LectoresBeta\User\AuthorPage\Application\DTO\AuthorPageStyleView;
use LectoresBeta\User\AuthorPage\Application\Handler\GetMyAuthorPageStyleHandler;
use LectoresBeta\User\AuthorPage\Application\Handler\UpdateAuthorPageStyleHandler;
use LectoresBeta\User\AuthorPage\Application\Query\GetMyAuthorPageStyle;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET` y `PUT /api/v1/me/author-page-style` (`FEAT-USR-016`).
 *
 * Un tema y un color, los dos **de un catálogo cerrado**. No se acepta CSS y
 * no se acepta un hexadecimal: lo que el autor elige es un código, y lo que
 * se sirve son estilos que escribió el equipo. Ver `AuthorPageTheme` para la
 * razón larga.
 *
 * El fondo no está aquí: es la portada del perfil, que tiene su propio
 * recurso porque es una subida de fichero.
 */
#[AsController]
final readonly class AuthorPageStyleController
{
    public function __construct(
        private GetMyAuthorPageStyleHandler $mine,
        private UpdateAuthorPageStyleHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        if (!$request->isMethod('PUT')) {
            return self::respond(($this->mine)(new GetMyAuthorPageStyle($user->getUserIdentifier())));
        }

        $body = JsonBody::of($request);

        return self::respond(($this->update)(new UpdateAuthorPageStyle(
            $user->getUserIdentifier(),
            // Ausente no cambia nada: la pantalla tiene dos selectores y se
            // mueve uno cada vez.
            $body->has('theme'),
            $body->string('theme'),
            $body->has('accentColour'),
            $body->string('accentColour'),
        )));
    }

    private static function respond(AuthorPageStyleView $view): Response
    {
        return new JsonResponse([
            'theme' => $view->theme->value,
            'accentColour' => $view->accentColour->value,
            'updatedAt' => $view->updatedAt->format(\DATE_ATOM),
        ]);
    }
}
