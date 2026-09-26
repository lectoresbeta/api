<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Enum;

/**
 * Where a work stands in its life (`FEAT-WRK-016`).
 *
 * `PUBLISHED` and `IN_CORRECTION` answer different questions. Published means
 * it can be read; in correction means new feedback is accepted (`RN-5`,
 * `RN-6`). The author opens that second door deliberately, because receiving
 * corrections costs credits — which is what the interface calls «poner tus
 * obras en corrección».
 *
 * The state is **not** the same axis as visibility. `W-9` settled that the
 * two coexist: a published work can be pulled from view for a week without
 * going back to being a draft, losing its history or its corrections. That
 * second axis is not modelled yet — nothing reads works, so there is nothing
 * for it to hide from — and the state is called `PUBLISHED` rather than
 * `VISIBLE` precisely so that adding it later cannot produce the nonsense of
 * a work that is `VISIBLE` and hidden at the same time.
 */
enum WorkStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case IN_CORRECTION = 'IN_CORRECTION';

    public function isReadableByOthers(): bool
    {
        return self::DRAFT !== $this;
    }

    public function acceptsNewCorrections(): bool
    {
        return self::IN_CORRECTION === $this;
    }
}
