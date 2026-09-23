<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\ValueObject\OverdraftGrantId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;

/**
 * One correction deliberately allowed on a chapter whose author cannot pay
 * for it (`FEAT-CRD-019`).
 *
 * It adds no mechanics: it provokes on purpose the situation a race already
 * produces. The author ends up negative and the correction arrives locked,
 * which is exactly what `FEAT-CRD-018` already describes.
 *
 * Granted by a periodic quota — three a week is the working figure
 * (`C-42`) — and the week is stored so the quota can be counted without
 * scanning dates.
 */
class OverdraftGrant
{
    private string $id;

    private string $authorId;

    private string $chapterId;

    /** Stored as `2026-W39`, so counting a week's grants is an equality. */
    private string $quotaPeriod;

    private int $amount;

    private \DateTimeImmutable $grantedAt;

    private ?\DateTimeImmutable $settledAt = null;

    public function __construct(
        OverdraftGrantId $id,
        UserId $authorId,
        ChapterId $chapterId,
        int $amount,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->authorId = $authorId->value();
        $this->chapterId = $chapterId->value();
        $this->amount = $amount;
        $this->grantedAt = $now;
        $this->quotaPeriod = $now->format('o-\WW');
    }

    public function id(): OverdraftGrantId
    {
        return OverdraftGrantId::fromString($this->id);
    }

    public function authorId(): UserId
    {
        return UserId::fromString($this->authorId);
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function quotaPeriod(): string
    {
        return $this->quotaPeriod;
    }

    public function isSettled(): bool
    {
        return null !== $this->settledAt;
    }

    /**
     * The author topped up and is out of debt. An overdraft that is never
     * settled is issuance, and that is what `FEAT-CRD-012` watches.
     */
    public function settle(\DateTimeImmutable $now): void
    {
        $this->settledAt ??= $now;
    }
}
