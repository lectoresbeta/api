<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Domain\Repository;

use LectoresBeta\Community\Mention\Domain\Entity\Mention;
use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;

interface MentionRepository
{
    public function save(Mention $mention): void;

    public function remove(Mention $mention): void;

    /**
     * Las menciones de estos sujetos, indexadas por identificador de sujeto.
     *
     * Por lotes porque se pinta una página entera: una consulta por fila
     * sería un N+1 escondido detrás de un método con buen nombre.
     *
     * @param list<string> $subjectIds
     *
     * @return array<string, list<Mention>>
     */
    public function of(MentionSubject $kind, array $subjectIds): array;
}
