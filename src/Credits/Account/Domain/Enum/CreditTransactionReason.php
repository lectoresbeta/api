<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Enum;

/**
 * Why a movement happened (`decision:0006`).
 *
 * The distinction that matters is between a **tap** — credits that enter the
 * economy from nowhere — and a **transfer**, which only moves them. The whole
 * accounting invariant of the context rests on it (`RN-11`): the sum of every
 * balance equals the taps minus the unrecovered overdraft. If a transfer were
 * ever counted as a tap, somebody would be holding credits nobody paid for.
 */
enum CreditTransactionReason: string
{
    case WELCOME_GRANT = 'WELCOME_GRANT';
    case INVITATION_REWARD = 'INVITATION_REWARD';

    case CORRECTION_CHARGED = 'CORRECTION_CHARGED';
    case CORRECTION_EARNED = 'CORRECTION_EARNED';
    case TIP_SENT = 'TIP_SENT';
    case TIP_RECEIVED = 'TIP_RECEIVED';

    case CLAIM_REVERSAL_REFUND = 'CLAIM_REVERSAL_REFUND';
    case CLAIM_REVERSAL_CHARGE = 'CLAIM_REVERSAL_CHARGE';

    case MANUAL_ADJUSTMENT = 'MANUAL_ADJUSTMENT';

    /**
     * A tap creates credits. Everything else moves them between two accounts
     * and nets to zero.
     */
    public function isTap(): bool
    {
        return match ($this) {
            self::WELCOME_GRANT, self::INVITATION_REWARD, self::MANUAL_ADJUSTMENT => true,
            default => false,
        };
    }
}
