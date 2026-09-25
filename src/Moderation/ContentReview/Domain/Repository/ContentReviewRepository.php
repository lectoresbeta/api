<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Domain\Repository;

use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\ContentReview\Domain\Entity\ContentReview;

interface ContentReviewRepository
{
    public function save(ContentReview $review): void;

    /**
     * El historial de veredictos sobre un texto, del más reciente al más
     * antiguo (`RN-3`).
     *
     * Se guardan **todos** y no solo el último: cuando haya varias
     * generaciones de revisor, la pregunta que alguien hará es «¿esto pasó
     * con las reglas de entonces o con las de ahora?».
     *
     * @return list<ContentReview>
     */
    public function historyOf(ClaimTargetType $targetType, string $targetId): array;

    /**
     * Si ese texto exacto ya se revisó (`RN-9`).
     *
     * Lo que hace idempotente la revisión, y por una clave mejor que el
     * identificador del hecho: **el propio texto**. Así una reentrega no
     * revisa dos veces, dos hechos distintos sobre el mismo texto tampoco, y
     * una edición que no cambia nada tampoco — que es lo que «solo lo que
     * cambia» quiere decir.
     */
    public function wasReviewed(ClaimTargetType $targetType, string $targetId, string $contentHash): bool;
}
