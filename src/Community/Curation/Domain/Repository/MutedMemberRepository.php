<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Domain\Repository;

use LectoresBeta\Community\Curation\Domain\Entity\MutedMember;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface MutedMemberRepository
{
    public function save(MutedMember $muted): void;

    public function remove(MemberId $memberId, MemberId $mutedId): void;

    public function has(MemberId $memberId, MemberId $mutedId): bool;

    /**
     * A quién ha silenciado esta persona, para quitarlo de la consulta del
     * muro.
     *
     * **Una sola dirección**, al revés que el bloqueo: silenciar es
     * unilateral también en el efecto. Que yo no quiera leerte no significa
     * que tú no puedas leerme.
     *
     * @return list<string>
     */
    public function mutedBy(MemberId $memberId): array;

    /**
     * La lista que se le enseña, de lo más reciente a lo más antiguo, con una
     * fila de más para saber si hay página siguiente.
     *
     * @return list<MutedMember>
     */
    public function pageOf(MemberId $memberId, ?Cursor $after, int $limit): array;
}
