<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Infrastructure\Controller;

use LectoresBeta\Community\Messaging\Application\Command\MarkConversationRead;
use LectoresBeta\Community\Messaging\Application\Handler\MarkConversationReadHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/conversations/{conversationId}/read` (`FEAT-COM-012`).
 *
 * `PUT` porque **fija un estado**: la conversación acaba leída, se llame una
 * vez o cinco. La pantalla marca al abrir y también con un gesto, así que la
 * segunda llamada llega sola y no debe significar nada distinto.
 *
 * Devuelve el contador —que es cero— para que el cliente repinte sin
 * recargar la lista entera.
 */
#[AsController]
final readonly class MarkConversationReadController
{
    public function __construct(
        private MarkConversationReadHandler $read,
        private Security $security,
    ) {
    }

    public function __invoke(string $conversationId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'unreadCount' => ($this->read)(new MarkConversationRead(
                $user->getUserIdentifier(),
                $conversationId,
            )),
        ]);
    }
}
