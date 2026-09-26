<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Http;

use LectoresBeta\User\AuthorPage\Application\DTO\AwardView;

/**
 * La forma que tiene un premio en la API (`FEAT-USR-030`).
 */
final class AwardBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(AwardView $award): array
    {
        return [
            'awardId' => $award->awardId,
            'title' => $award->title,
            'awardedBy' => $award->awardedBy,
            'year' => $award->year,
            'note' => $award->note,
            'url' => $award->url,
            'urlIsExternal' => $award->urlIsExternal,
        ];
    }

    /**
     * @param list<AwardView> $awards
     *
     * @return array<string, mixed>
     */
    public static function listOf(array $awards): array
    {
        return ['awards' => array_map(self::of(...), $awards)];
    }
}
