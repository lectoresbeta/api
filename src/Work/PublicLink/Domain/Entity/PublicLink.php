<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\PublicLink\Domain\ValueObject\PublicLinkId;

/**
 * Una puerta abierta a una obra inédita, sin sesión (`FEAT-WRK-010`).
 *
 * Las correcciones que llegan por ella están **fuera de la economía**: ni
 * cuestan ni pagan, y `Credits` ni siquiera consume su evento
 * ([`FEAT-FBK-008`](../../../../../docs/features/feedback/FEAT-FBK-008-public-link-correction.md)).
 * Es la forma más clara de decirlo: al otro lado no hay cuenta a la que
 * abonar.
 *
 * **El token se guarda cifrado y no se puede recuperar** (`RN-2`). Quien
 * pierde la URL crea otro enlace; la alternativa —guardarlo en claro para
 * poder enseñarlo siempre— dejaría una credencial legible en la base de datos
 * y en cada copia de seguridad, y eso no se deshace.
 *
 * Lo que esta entidad **no** sabe es cuántas correcciones se han gastado. Las
 * tiene `Feedback`, que es quien las recibe; una copia aquí sería un segundo
 * número que se queda viejo.
 */
class PublicLink
{
    /**
     * Diez cubre el caso real —repartirlo a tu grupo de escritura— y limita
     * el daño si la URL circula más de la cuenta (`RN-4`, `C-36`).
     */
    public const DEFAULT_MAX_CORRECTIONS = 10;

    public const MAX_CORRECTIONS_CEILING = 100;

    private string $id;

    private string $workId;

    private string $tokenHash;

    private ?string $label = null;

    private int $maxCorrections;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $revokedAt = null;

    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct(
        PublicLinkId $id,
        WorkId $workId,
        string $tokenHash,
        \DateTimeImmutable $now,
        int $maxCorrections = self::DEFAULT_MAX_CORRECTIONS,
        ?\DateTimeImmutable $expiresAt = null,
        ?string $label = null,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->tokenHash = $tokenHash;
        $this->createdAt = $now;
        $this->maxCorrections = $maxCorrections;
        $this->expiresAt = $expiresAt;
        $this->label = $label;
    }

    public function id(): PublicLinkId
    {
        return PublicLinkId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function maxCorrections(): int
    {
        return $this->maxCorrections;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isUsableAt(\DateTimeImmutable $moment): bool
    {
        if (null !== $this->revokedAt) {
            return false;
        }

        return null === $this->expiresAt || $moment < $this->expiresAt;
    }

    /**
     * `RN-9`: inmediata e irreversible. Revocar dos veces no es un error —el
     * enlace acaba igual de inservible— pero la fecha es la primera, que es
     * la que responde «desde cuándo dejó de servir».
     */
    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }
}
