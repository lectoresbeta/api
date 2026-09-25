<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Infrastructure\Controller;

use LectoresBeta\Moderation\Review\Application\Command\ReplyToModeration;
use LectoresBeta\Moderation\Review\Application\DTO\ThreadMessage;
use LectoresBeta\Moderation\Review\Application\Handler\ListMyClaimThreadHandler;
use LectoresBeta\Moderation\Review\Application\Handler\ReplyToModerationHandler;
use LectoresBeta\Moderation\Review\Application\Query\ListMyClaimThread;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET` y `POST /api/v1/me/claims/{claimId}/messages` (`FEAT-MOD-009`).
 *
 * **No hay forma de elegir hilo.** El suyo se deduce de quién pregunta: con
 * un identificador de hilo en la ruta, leer el de la otra parte sería cambiar
 * una palabra en la dirección, y esta funcionalidad existe precisamente para
 * que eso no se pueda.
 *
 * Los dos verbos comparten controlador porque comparten el recurso: lo que
 * cambia es si se lee o se escribe.
 */
#[AsController]
final readonly class MyClaimThreadController
{
    public function __construct(
        private ListMyClaimThreadHandler $thread,
        private ReplyToModerationHandler $reply,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $claimId = (string) $request->attributes->get('claimId');

        if ($request->isMethod('POST')) {
            $messageId = ($this->reply)(new ReplyToModeration(
                $claimId,
                $user->getUserIdentifier(),
                JsonBody::of($request)->string('body'),
            ));

            return new JsonResponse(['messageId' => $messageId], Response::HTTP_CREATED);
        }

        return new JsonResponse(['messages' => array_map(
            static fn (ThreadMessage $message): array => [
                'messageId' => $message->messageId,
                // Sin identidad: la conversación no revela quién es el
                // moderador, y firma como «Moderación».
                'from' => $message->fromModeration ? 'MODERATION' : 'ME',
                'body' => $message->body,
                'sentAt' => $message->sentAt->format(\DATE_ATOM),
            ],
            ($this->thread)(new ListMyClaimThread($claimId, $user->getUserIdentifier())),
        )]);
    }
}
