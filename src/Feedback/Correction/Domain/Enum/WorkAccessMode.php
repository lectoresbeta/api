<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Enum;

/**
 * How open a work is to beta readers, **as `Feedback` understands it**.
 *
 * A deliberate copy of a concept `Work` owns, and not an import: the same
 * business idea may have a different representation in each context, and
 * sharing the enum would make one context's model the other's dependency.
 * What travels between them is the value, not the class.
 *
 * Here it answers one question only: **does starting a correction grant the
 * access by itself?** `Work` uses the same three values to decide other
 * things this context never needs to know.
 */
enum WorkAccessMode: string
{
    case PUBLIC = 'PUBLIC';
    case ON_REQUEST = 'ON_REQUEST';
    case PRIVATE = 'PRIVATE';

    /**
     * Anything unrecognised is treated as the strictest option. A value this
     * context does not know is not a reason to let somebody in.
     */
    public static function orStrictest(string $value): self
    {
        return self::tryFrom($value) ?? self::PRIVATE;
    }

    /**
     * In a `PUBLIC` work the act of starting is itself the permission
     * (`FEAT-FBK-003` `R-4`): the author already said «anybody may», and
     * asking them again would be asking permission of somebody who already
     * gave it.
     */
    public function grantsAccessOnStart(): bool
    {
        return self::PUBLIC === $this;
    }
}
