<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionPrice;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;

/**
 * The quotations of the corrections under way.
 *
 * Not holds: nothing is set aside and there is no state to reconcile
 * (`decision:0006`). A quotation is dropped when the draft is discarded and
 * consumed when the correction is delivered.
 */
interface CorrectionPriceRepository
{
    public function save(CorrectionPrice $price): void;

    public function quoted(ChapterId $chapterId, UserId $readerId): ?CorrectionPrice;

    public function discard(CorrectionPrice $price): void;

    /**
     * How many corrections are open on a chapter. It is the lever that caps
     * the overdraft caused by a race **without setting any credits aside**
     * (`C-41`).
     */
    public function openCorrectionsOn(ChapterId $chapterId): int;
}
