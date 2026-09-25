<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Service\OverdraftPolicy;
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

    /**
     * Hasta cuándo sirve la elegibilidad.
     *
     * **La elegibilidad caduca y no se acumula** (`RN-2`): si nadie corrigió
     * a ese autor esta semana, la semana que viene empieza de cero. Un cupo
     * que se arrastra deja de ser un techo.
     */
    private \DateTimeImmutable $expiresAt;

    /**
     * Cuándo se usó de verdad, es decir, cuándo alguien corrigió ese capítulo
     * y la deuda apareció.
     *
     * Nulo mientras sea solo elegibilidad, que es la distinción que hace que
     * el cupo sea **un techo de elegibilidad y no de deuda realizada**: la
     * deuda solo existe si alguien decide corregir.
     */
    private ?\DateTimeImmutable $usedAt = null;

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
        $this->quotaPeriod = OverdraftPolicy::periodOf($now);
        $this->expiresAt = OverdraftPolicy::expiryOf($now);
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

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Si ese capítulo puede recibir ahora mismo una corrección que su autor
     * no puede pagar.
     *
     * Ya usada deja de servir: **una sola por autor** (`RN-1`). Sale además
     * de `RN-2` de `FEAT-CRD-018` —con saldo negativo no se reciben más— pero
     * comprobarlo aquí es lo que hace que la regla no dependa de que otra
     * regla siga existiendo.
     */
    public function isUsableAt(\DateTimeImmutable $moment): bool
    {
        return null === $this->usedAt && $moment < $this->expiresAt;
    }

    public function wasUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function markUsed(\DateTimeImmutable $now): void
    {
        $this->usedAt ??= $now;
    }

    public function isSettled(): bool
    {
        return null !== $this->settledAt;
    }

    /**
     * The author topped up and is out of debt. An overdraft that is never
     * settled is issuance, and that is what `FEAT-CRD-012` watches.
     *
     * Solo cuenta como saldado lo que llegó a usarse: una elegibilidad que
     * nadie aprovechó no emitió nada, y contarla como recuperada inflaría la
     * única cifra que dice si esto funciona.
     */
    public function settle(\DateTimeImmutable $now): void
    {
        if (null === $this->usedAt) {
            return;
        }

        $this->settledAt ??= $now;
    }
}
