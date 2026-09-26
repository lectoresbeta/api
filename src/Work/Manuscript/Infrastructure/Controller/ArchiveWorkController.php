<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Manuscript\Application\Command\ArchiveWork;
use LectoresBeta\Work\Manuscript\Application\Handler\ArchiveWorkHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/works/{workId}` (`FEAT-WRK-006`).
 *
 * `DELETE` porque es lo que el autor cree que hace y lo que la interfaz llama
 * «Eliminar». Lo que ocurre es un **archivado**, y el diálogo de
 * confirmación tiene que decirlo con esas palabras — decir «esta acción no se
 * puede deshacer» sobre algo que sí se deshace enseña a no leer los diálogos.
 *
 * La confirmación viaja en el cuerpo: un `DELETE` que se dispara por un
 * enlace mal pulsado no debería vaciar un perfil.
 */
#[AsController]
final readonly class ArchiveWorkController
{
    public function __construct(
        private ArchiveWorkHandler $archive,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->archive)(new ArchiveWork(
            $workId,
            $author->getUserIdentifier(),
            true === JsonBody::of($request)->bool('confirm'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
