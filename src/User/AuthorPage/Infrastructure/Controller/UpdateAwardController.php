<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\AuthorPage\Application\Command\UpdateAward;
use LectoresBeta\User\AuthorPage\Application\Handler\UpdateAwardHandler;
use LectoresBeta\User\AuthorPage\Infrastructure\Http\AwardBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PATCH /api/v1/me/awards/{awardId}` (`FEAT-USR-030`).
 *
 * Lo que no se envía se queda como estaba; enviar `note: null` lo borra. La
 * diferencia la hace la presencia de la clave, no su valor.
 */
#[AsController]
final readonly class UpdateAwardController
{
    public function __construct(
        private UpdateAwardHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $awardId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        return new JsonResponse(AwardBody::of(($this->update)(new UpdateAward(
            $user->getUserIdentifier(),
            $awardId,
            $body->has('title'),
            $body->string('title'),
            $body->has('awardedBy'),
            $body->string('awardedBy'),
            $body->has('year'),
            $body->int('year'),
            $body->has('note'),
            $body->string('note'),
            $body->has('url'),
            $body->string('url'),
        ))));
    }
}
