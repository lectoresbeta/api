<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Controller;

use LectoresBeta\Community\Post\Application\Service\VisiblePost;
use LectoresBeta\Community\Post\Domain\Exception\PostNotFound;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Shared\Application\Storage\FileStorage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/posts/{postId}/image` (`FEAT-COM-002`).
 *
 * La imagen **no se sirve desde la carpeta pública**, y esa es la decisión
 * que hay detrás de este controlador. Una publicación para seguidores es una
 * regla de privacidad; si su foto viviera en `media/`, quedaría protegida
 * solo por lo difícil que es adivinar una clave, y una clave se filtra el día
 * que aparece en un registro, en un `Referer` o en una captura de pantalla.
 *
 * Así que se pide por la publicación y se comprueba **lo mismo que comprueba
 * el muro**: si esa publicación no existe para quien pregunta, su imagen
 * tampoco.
 *
 * El coste es real y conviene tenerlo escrito: esto no se puede poner detrás
 * de una caché compartida, porque la respuesta depende de quién pregunta.
 */
#[AsController]
final readonly class GetPostImageController
{
    public function __construct(
        private VisiblePost $visible,
        private PostRepository $posts,
        private FileStorage $storage,
        private Security $security,
    ) {
    }

    public function __invoke(string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $post = $this->visible->to($postId, $user->getUserIdentifier());
        $attachment = $this->posts->attachmentsOf([$post->id()->value()])[$post->id()->value()] ?? null;

        if (null === $attachment) {
            throw PostNotFound::create();
        }

        $file = $this->storage->read($attachment->url());

        if (null === $file) {
            throw PostNotFound::create();
        }

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->contentType,
            // Privada: la respuesta depende de quién pregunta, así que una
            // caché compartida serviría la foto de una publicación cerrada a
            // quien no debía verla.
            'Cache-Control' => 'private, max-age=3600',
            // El fichero se sirve, nunca se interpreta.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
