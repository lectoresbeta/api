<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Query;

/**
 * Whose balance. There is no other parameter, and there is no version of this
 * that takes somebody else's identifier (`FEAT-CRD-001`).
 */
final readonly class GetCreditBalance
{
    public function __construct(public string $userId)
    {
    }
}
