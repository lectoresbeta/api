<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Controller;

use LectoresBeta\Community\Post\Application\Command\CreatePost;
use LectoresBeta\Community\Post\Application\Handler\CreatePostHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/posts` (`FEAT-COM-002`).
 *
 * Acepta las dos formas porque las dos hacen falta: `multipart/form-data`
 * cuando hay imagen —un fichero no cabe en un JSON sin inflarlo un tercio— y
 * JSON cuando no. Lo que cambia es el sobre; lo que llega a Application es el
 * mismo comando.
 *
 * Aquí termina lo que Symfony sabe de una subida: a Application pasan bytes.
 */
#[AsController]
final readonly class CreatePostController
{
    public function __construct(
        private CreatePostHandler $create,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $image = $request->files->get('image');
        $fields = $image instanceof UploadedFile || $request->request->count() > 0
            ? null
            : JsonBody::of($request);

        $postId = ($this->create)(new CreatePost(
            $user->getUserIdentifier(),
            self::field($request, $fields, 'body'),
            self::field($request, $fields, 'type'),
            self::field($request, $fields, 'audience'),
            $image instanceof UploadedFile ? (string) file_get_contents($image->getPathname()) : null,
            self::field($request, $fields, 'linkUrl'),
            self::field($request, $fields, 'workId'),
        ));

        return new JsonResponse(['postId' => $postId], Response::HTTP_CREATED);
    }

    /**
     * El mismo campo, venga del formulario o del cuerpo JSON.
     *
     * Un valor que no sea texto responde `null` por lo mismo que en el resto
     * del proyecto: aceptar un tipo distinto del declarado convierte el
     * contrato en una sugerencia.
     */
    private static function field(Request $request, ?JsonBody $body, string $name): ?string
    {
        if (null !== $body) {
            return $body->string($name);
        }

        $value = $request->request->get($name);

        return \is_string($value) ? $value : null;
    }
}
