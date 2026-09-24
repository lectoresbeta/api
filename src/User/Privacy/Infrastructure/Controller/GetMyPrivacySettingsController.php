<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Infrastructure\Controller;

use LectoresBeta\User\Privacy\Application\Handler\GetMyPrivacySettingsHandler;
use LectoresBeta\User\Privacy\Application\Query\GetMyPrivacySettings;
use LectoresBeta\User\Privacy\Infrastructure\Http\PrivacySettingsBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/privacy-settings` (`FEAT-USR-038`).
 *
 * Solo los propios: **no hay endpoint para los ajenos**. Su efecto se ve en
 * las respuestas de los demás endpoints, no preguntando por ellos.
 */
#[AsController]
final readonly class GetMyPrivacySettingsController
{
    public function __construct(
        private GetMyPrivacySettingsHandler $settings,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(PrivacySettingsBody::of(
            ($this->settings)(new GetMyPrivacySettings($user->getUserIdentifier())),
        ));
    }
}
