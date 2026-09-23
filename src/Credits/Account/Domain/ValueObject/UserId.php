<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * The account holder, as this context knows them.
 *
 * Deliberately a different class from `User\Account\Domain\ValueObject\UserId`
 * even though both hold the same UUID. Credits must not depend on `User`
 * (`AGENTS.md`, `decision:0002`), and the value arrives here through an
 * integration event, not through a shared model.
 */
final readonly class UserId extends Uuid
{
}
