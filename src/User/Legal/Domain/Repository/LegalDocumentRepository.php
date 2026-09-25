<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Domain\Repository;

use LectoresBeta\User\Legal\Domain\Entity\LegalDocument;
use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;

interface LegalDocumentRepository
{
    public function save(LegalDocument $document): void;

    /**
     * El documento vigente de ese tipo: el de mayor fecha de entrada en vigor
     * que ya haya entrado.
     *
     * Publicar la política del mes que viene no debe cambiar lo que se acepta
     * hoy, así que una fecha futura no cuenta todavía.
     */
    public function inForce(LegalDocumentType $type, \DateTimeImmutable $now): ?LegalDocument;

    /**
     * Todos los vigentes, uno por tipo.
     *
     * @return list<LegalDocument>
     */
    public function allInForce(\DateTimeImmutable $now): array;
}
