<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * Somebody involved in a claim: whoever reported it, whoever it is about, or
 * the moderator. Identifiers only; `Moderation` owns no accounts.
 */
final readonly class PartyId extends Uuid
{
}
