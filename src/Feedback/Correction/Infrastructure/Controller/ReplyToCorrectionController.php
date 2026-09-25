<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Command\DeleteCorrectionReply;
use LectoresBeta\Feedback\Correction\Application\Command\ReplyToCorrection;
use LectoresBeta\Feedback\Correction\Application\Handler\DeleteCorrectionReplyHandler;
use LectoresBeta\Feedback\Correction\Application\Handler\ReplyToCorrectionHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT` y `DELETE /api/v1/corrections/{correctionId}/reply` (`FEAT-FBK-005`).
 *
 * `PUT` porque es **una** respuesta y no una lista: enviarla dos veces deja
 * una, que es lo que el autor espera al corregir una errata en lo que acaba
 * de escribir.
 *
 * Las dos operaciones comparten controlador porque comparten todo lo demás
 * —la ruta, la autorización y el recurso— y separarlas sería duplicar la
 * puerta para cambiar el verbo.
 */
#[AsController]
final readonly class ReplyToCorrectionController
{
    public function __construct(
        private ReplyToCorrectionHandler $reply,
        private DeleteCorrectionReplyHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $correctionId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        if ($request->isMethod('DELETE')) {
            ($this->delete)(new DeleteCorrectionReply($correctionId, $author->getUserIdentifier()));

            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        ($this->reply)(new ReplyToCorrection(
            $correctionId,
            $author->getUserIdentifier(),
            (string) JsonBody::of($request)->string('body'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
