<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;
use LectoresBeta\User\Legal\Domain\ValueObject\LegalAcceptanceId;

/**
 * Proof that somebody accepted a specific version of a document.
 *
 * Immutable by construction: it has no mutator. If the wording changes, the
 * answer is a new acceptance, never an edit — the record exists precisely to
 * be quoted back.
 */
class LegalAcceptance
{
    private string $id;

    private string $userId;

    private LegalDocumentType $documentType;

    private string $version;

    private \DateTimeImmutable $acceptedAt;

    /**
     * Kept because consent has to be provable, and «they clicked it» is not
     * proof on its own.
     */
    private ?string $ipAddress = null;

    public function __construct(
        LegalAcceptanceId $id,
        UserId $userId,
        LegalDocumentType $documentType,
        string $version,
        \DateTimeImmutable $acceptedAt,
        ?string $ipAddress = null,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->documentType = $documentType;
        $this->version = $version;
        $this->acceptedAt = $acceptedAt;
        $this->ipAddress = $ipAddress;
    }

    public function id(): LegalAcceptanceId
    {
        return LegalAcceptanceId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function documentType(): LegalDocumentType
    {
        return $this->documentType;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function acceptedAt(): \DateTimeImmutable
    {
        return $this->acceptedAt;
    }
}
