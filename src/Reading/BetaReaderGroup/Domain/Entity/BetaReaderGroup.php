<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\Entity;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupName;

/**
 * Una lista de lectores beta que el autor mantiene para sí (`FEAT-RDG-007`).
 *
 * **Pertenecer a un grupo no concede nada** (`RN-11`, cierra `R-4`). El acceso
 * a una obra sigue naciendo por los tres caminos de siempre y siempre a
 * nombre de una persona. Esto es una agenda: sirve para no volver a buscar a
 * las mismas diez personas obra tras obra, y para **invitarlas de una vez**
 * (`RN-12`), que es otra cosa — una invitación por miembro, cada una con sus
 * comprobaciones y su respuesta.
 *
 * Es privada. El miembro no sabe que está en un grupo, ni cómo se llama:
 * es una anotación del autor sobre otras personas, no una relación entre ellas.
 */
class BetaReaderGroup
{
    private string $id;

    private string $authorId;

    private string $name;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        BetaReaderGroupId $id,
        AuthorId $authorId,
        BetaReaderGroupName $name,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->authorId = $authorId->value();
        $this->name = $name->value();
        $this->createdAt = $now;
    }

    public function id(): BetaReaderGroupId
    {
        return BetaReaderGroupId::fromString($this->id);
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function name(): BetaReaderGroupName
    {
        return BetaReaderGroupName::fromString($this->name);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function belongsTo(AuthorId $authorId): bool
    {
        return $this->authorId === $authorId->value();
    }

    public function rename(BetaReaderGroupName $name): void
    {
        $this->name = $name->value();
    }
}
