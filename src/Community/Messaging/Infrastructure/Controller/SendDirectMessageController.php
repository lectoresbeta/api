<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Infrastructure\Controller;

use LectoresBeta\Community\Messaging\Application\Command\SendDirectMessage;
use LectoresBeta\Community\Messaging\Application\Handler\SendDirectMessageHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/users/{userId}/messages` (`FEAT-COM-011`).
 *
 * **Se escribe a una persona, no a una conversación.** Quien pulsa «Enviar
 * mensaje» en un perfil no sabe si ya hay hilo abierto, y obligarle a
 * averiguarlo —o a crearlo— sería trasladarle al cliente una decisión que el
 * servidor puede tomar: abrir o continuar es la misma operación vista desde
 * fuera.
 *
 * Devuelve `conversationId` porque es lo que la pantalla necesita para entrar
 * en el hilo recién abierto sin una segunda petición.
 */
#[AsController]
final readonly class SendDirectMessageController
{
    public function __construct(
        private SendDirectMessageHandler $send,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $userId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $sent = ($this->send)(new SendDirectMessage(
            $user->getUserIdentifier(),
            $userId,
            JsonBody::of($request)->string('body'),
        ));

        return new JsonResponse([
            'conversationId' => $sent->conversationId,
            'messageId' => $sent->messageId,
            'sentAt' => $sent->sentAt->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }
}
