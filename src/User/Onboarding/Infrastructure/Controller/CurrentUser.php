<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Infrastructure\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Who is asking, for the controllers of this concept.
 *
 * The firewall has already refused anonymous requests on these routes, so
 * reaching here without a user means the configuration has drifted. Failing
 * loudly beats a null identifier travelling into a use case and coming back
 * as a confusing «no such account».
 */
final readonly class CurrentUser
{
    public static function identifierFrom(Security $security): string
    {
        $user = $security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return $user->getUserIdentifier();
    }
}
