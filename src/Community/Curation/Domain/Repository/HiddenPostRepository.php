<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Domain\Repository;

use LectoresBeta\Community\Curation\Domain\Entity\HiddenPost;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

interface HiddenPostRepository
{
    public function save(HiddenPost $hidden): void;

    public function remove(MemberId $memberId, PostId $postId): void;

    /**
     * Qué ha escondido esta persona, para quitarlo de la consulta del muro.
     *
     * @return list<string>
     */
    public function hiddenBy(MemberId $memberId): array;
}
