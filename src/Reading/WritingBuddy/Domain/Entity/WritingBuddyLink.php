<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Domain\Entity;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Domain\Enum\WritingBuddyStatus;
use LectoresBeta\Reading\WritingBuddy\Domain\ValueObject\WritingBuddyLinkId;

/**
 * A reciprocal tie between two people.
 *
 * The pair is stored **sorted**, so that (A, B) and (B, A) are the same row
 * and the unique index actually means «one live link per pair». Who proposed
 * is kept in its own column, because that is what the sorting throws away.
 *
 * **El vínculo no habilita nada por sí solo** (`R-3`, resuelta en
 * `FEAT-RDG-008`). No concede acceso de lector beta a las obras del otro, ni
 * salta la modalidad que cada autor eligió, ni la clasificación por edad. Es
 * un vínculo declarado: se ve, se anuncia y ahí acaba.
 *
 * La alternativa —acceso mutuo automático— era cómoda y abría una puerta que
 * no pasa por `AccessRequest` ni por `AccessInvitation`: dos personas
 * podrían concederse entre ellas lo que el modelo de acceso entero está
 * hecho para gobernar, y de paso saltarse `ADULTS_ONLY`. Quien quiera leer
 * al otro lo invita, que cuesta un clic.
 */
class WritingBuddyLink
{
    private string $id;

    private string $memberOne;

    private string $memberTwo;

    private string $proposedBy;

    private WritingBuddyStatus $status;

    private \DateTimeImmutable $proposedAt;

    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(
        WritingBuddyLinkId $id,
        ReaderId $proposer,
        ReaderId $partner,
        \DateTimeImmutable $now,
    ) {
        $pair = [$proposer->value(), $partner->value()];
        sort($pair);

        $this->id = $id->value();
        $this->memberOne = $pair[0];
        $this->memberTwo = $pair[1];
        $this->proposedBy = $proposer->value();
        $this->status = WritingBuddyStatus::PROPOSED;
        $this->proposedAt = $now;
    }

    public function id(): WritingBuddyLinkId
    {
        return WritingBuddyLinkId::fromString($this->id);
    }

    public function proposedBy(): ReaderId
    {
        return ReaderId::fromString($this->proposedBy);
    }

    public function status(): WritingBuddyStatus
    {
        return $this->status;
    }

    public function involves(ReaderId $reader): bool
    {
        return \in_array($reader->value(), [$this->memberOne, $this->memberTwo], true);
    }

    /**
     * La otra parte. Lo pregunta la lista, que enseña con quién es cada
     * vínculo, y quien avisa, que necesita saber a quién.
     */
    public function otherThan(ReaderId $reader): ReaderId
    {
        return ReaderId::fromString($reader->value() === $this->memberOne ? $this->memberTwo : $this->memberOne);
    }

    /**
     * Quien recibió la propuesta, que es el único que puede resolverla:
     * el par se guarda ordenado y eso es justo lo que el orden tira.
     */
    public function proposedTo(): ReaderId
    {
        return $this->otherThan($this->proposedBy());
    }

    public function proposedAt(): \DateTimeImmutable
    {
        return $this->proposedAt;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function accept(\DateTimeImmutable $now): void
    {
        $this->resolve(WritingBuddyStatus::ACCEPTED, $now);
    }

    public function decline(\DateTimeImmutable $now): void
    {
        $this->resolve(WritingBuddyStatus::DECLINED, $now);
    }

    public function end(\DateTimeImmutable $now): void
    {
        $this->status = WritingBuddyStatus::ENDED;
        $this->resolvedAt = $now;
    }

    private function resolve(WritingBuddyStatus $status, \DateTimeImmutable $now): void
    {
        if (WritingBuddyStatus::PROPOSED !== $this->status) {
            return;
        }

        $this->status = $status;
        $this->resolvedAt = $now;
    }
}
