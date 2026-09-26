<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La agenda no admite eso, y este es el motivo (`FEAT-RDG-007`).
 *
 * Misma forma que `InvitationRefused` y por la misma razón: nadie las captura
 * una a una, todas acaban en un problema RFC 9457 con su código, y la especie
 * viaja por fábrica para que una misma idea pueda responder `422` o `409`.
 */
final class GroupRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function nameIsRequired(): self
    {
        return new self(
            'GROUP_NAME_REQUIRED',
            FailureKind::INVALID,
            'A beta reader group needs a name.',
        );
    }

    public static function nameIsTooLong(): self
    {
        return new self(
            'GROUP_NAME_TOO_LONG',
            FailureKind::INVALID,
            'That name is too long for a beta reader group.',
        );
    }

    /**
     * `RN-3`. Dos listas con el mismo nombre son dos entradas indistinguibles
     * en el desplegable donde el autor tiene que elegir una.
     */
    public static function nameAlreadyUsed(): self
    {
        return new self(
            'GROUP_NAME_ALREADY_USED',
            FailureKind::CONFLICT,
            'You already have a beta reader group with that name.',
        );
    }

    public static function tooManyGroups(): self
    {
        return new self(
            'TOO_MANY_GROUPS',
            FailureKind::CONFLICT,
            'You have reached the maximum number of beta reader groups.',
        );
    }

    /**
     * `RN-5`. Es una agenda, no una lista de difusión.
     */
    public static function tooManyMembers(): self
    {
        return new self(
            'TOO_MANY_GROUP_MEMBERS',
            FailureKind::CONFLICT,
            'That group has reached its maximum number of members.',
        );
    }

    public static function userNotFound(): self
    {
        return new self(
            'USER_NOT_FOUND',
            FailureKind::INVALID,
            'There is no such user to add to the group.',
        );
    }

    public static function authorCannotBeAMember(): self
    {
        return new self(
            'AUTHOR_CANNOT_BE_A_MEMBER',
            FailureKind::INVALID,
            'You do not put yourself in your own list of beta readers.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return $this->failureKind;
    }
}
