<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Controller;

use LectoresBeta\Community\Post\Application\Command\CreatePost;
use LectoresBeta\Community\Post\Application\Handler\CreatePostHandler;
use LectoresBeta\Community\Post\Domain\Exception\PostRefused;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Account\Application\Contract\PlatformAccount;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/admin/platform-posts` (`FEAT-COM-038`).
 *
 * Publicar **en nombre de la plataforma**, no en el propio. Quien administra
 * firma la acción; quien aparece en la tarjeta es la cuenta institucional.
 *
 * **Y no hay nada más.** La publicación que sale de aquí es una publicación
 * normal con audiencia `EVERYONE`, así que llega a todo el mundo sin seguir a
 * nadie por la regla que el muro ya tenía desde `FEAT-COM-001`: lo compone la
 * audiencia, no el seguimiento. Esa es la razón de modelar la cuenta
 * institucional como una cuenta y no como un tipo de publicación — no hace
 * falta una sola rama nueva en la consulta del muro.
 *
 * Se comenta, se apoya y se repostea igual que cualquier otra, por lo mismo.
 *
 * Cuelga de `/admin`, que exige rol, y el suyo es `ROLE_ADMIN` y no
 * `ROLE_MODERATOR`: hablar con la voz de la plataforma no es moderar.
 */
#[AsController]
final readonly class PublishPlatformPostController
{
    public function __construct(
        private CreatePostHandler $publish,
        private PlatformAccount $platform,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (null === $this->security->getUser()) {
            throw new UnauthorizedHttpException('Bearer');
        }

        // Sin cuenta designada no hay en nombre de quién publicar. Es un
        // estado normal de una instalación recién puesta en marcha, no una
        // avería, y se dice con claridad para que quien opere sepa que le
        // falta ejecutar un comando.
        $author = $this->platform->id() ?? throw PostRefused::withoutAPlatformAccount();

        $body = JsonBody::of($request);

        $postId = ($this->publish)(new CreatePost(
            $author,
            $body->string('body'),
            $body->string('type'),
            // La audiencia no se acepta del cuerpo: un anuncio de la
            // plataforma para seguidores no significa nada, porque a la
            // cuenta institucional no se la sigue para enterarse.
            'EVERYONE',
            null,
            $body->string('linkUrl'),
            $body->string('workId'),
        ));

        return new JsonResponse(['postId' => $postId], Response::HTTP_CREATED);
    }
}
