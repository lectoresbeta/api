<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Enum;

/**
 * What the author can see of a delivered correction.
 *
 * `LOCKED` is the negative-balance case (`FEAT-CRD-018`): the author sees
 * that the correction exists and who wrote it, but not its content, until
 * they top up. The corrector has already been paid either way (`RN-9`).
 *
 * Deciding this is **`Feedback`'s job, never `Credits`'**. `Credits`
 * publishes the economic fact; hiding or showing the text belongs to whoever
 * owns it.
 *
 * `HIDDEN_BY_AUTHOR` never hides it from the person who wrote it (`RN-6`).
 */
enum CorrectionVisibility: string
{
    case VISIBLE = 'VISIBLE';
    case LOCKED = 'LOCKED';
    case HIDDEN_BY_AUTHOR = 'HIDDEN_BY_AUTHOR';
}
