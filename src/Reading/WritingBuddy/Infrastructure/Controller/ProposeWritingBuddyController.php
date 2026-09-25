<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Infrastructure\Controller;

use LectoresBeta\Reading\WritingBuddy\Application\Command\ProposeWritingBuddy;
use LectoresBeta\Reading\WritingBuddy\Application\Handler\ProposeWritingBuddyHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/users/{userId}/writing-buddy-proposals` (`FEAT-RDG-008`).
 *
 * Se propone **a una persona**, así que cuelga de ella. El vínculo no existe
 * hasta que la otra parte acepta, y ni siquiera entonces abre ninguna puerta:
 * no concede acceso a las obras del otro (`R-3`).
 */
#[AsController]
final readonly class ProposeWritingBuddyController
{
    public function __construct(
        private ProposeWritingBuddyHandler $propose,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'linkId' => ($this->propose)(new ProposeWritingBuddy($user->getUserIdentifier(), $userId)),
        ], Response::HTTP_CREATED);
    }
}
