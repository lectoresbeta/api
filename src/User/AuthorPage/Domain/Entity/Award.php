<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Entity;

use LectoresBeta\Shared\Domain\ValueObject\WebAddress;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Exception\AwardRefused;
use LectoresBeta\User\AuthorPage\Domain\Service\AwardPolicy;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AwardId;

/**
 * Un mérito que el autor declara (`FEAT-USR-030`).
 *
 * Un premio, una mención, una beca, un finalista. **Lo declara él y no se
 * valida contra nada** (`RN-3`): no existe un registro universal de premios
 * literarios, y el concurso del ayuntamiento y el Premio Planeta se cuentan
 * igual. Lo que la plataforma ofrece no es una acreditación, es un sitio
 * donde ponerlo; el enlace, opcional, es lo que permite comprobarlo.
 *
 * Hermano de `PublishedBook` y **sin imagen**: un premio no tiene portada, y
 * añadir aquí un diploma sería otro tipo de fichero, otro límite y otra
 * pantalla por un adorno.
 *
 * Tampoco tiene `position`, al revés que una obra publicada (`RN-8`). Allí el
 * orden es una decisión del autor porque la cuadrícula es su escaparate; un
 * historial de premios se lee como un currículo, lo último primero, y una
 * lista que se puede reordenar es una lista donde el orden pasa a ser
 * información.
 */
class Award
{
    private string $id;

    private string $userId;

    private string $title;

    private ?string $awardedBy = null;

    private ?int $year = null;

    private ?string $note = null;

    private ?string $url = null;

    private \DateTimeImmutable $createdAt;

    private function __construct(AwardId $id, UserId $userId, string $title, \DateTimeImmutable $now)
    {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->title = self::acceptableTitle($title);
        $this->createdAt = $now;
    }

    public static function declare(AwardId $id, UserId $userId, string $title, \DateTimeImmutable $now): self
    {
        return new self($id, $userId, $title, $now);
    }

    public function id(): AwardId
    {
        return AwardId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function awardedBy(): ?string
    {
        return $this->awardedBy;
    }

    public function year(): ?int
    {
        return $this->year;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * `RN-1`. Lo pregunta el agregado y no cada caso de uso: una comprobación
     * repartida es una comprobación que faltará en algún sitio.
     */
    public function belongsTo(UserId $userId): bool
    {
        return $this->userId === $userId->value();
    }

    public function retitle(string $title): void
    {
        $this->title = self::acceptableTitle($title);
    }

    /**
     * **Texto libre** (`RN-3`), por lo mismo que la editorial de una obra
     * publicada: validar contra un catálogo dejaría fuera justo al perfil más
     * habitual de esta plataforma.
     */
    public function setAwardedBy(?string $awardedBy): void
    {
        $awardedBy = self::trimmedOrNull($awardedBy);

        if (null !== $awardedBy && mb_strlen($awardedBy) > AwardPolicy::GRANTOR_MAX_LENGTH) {
            throw AwardRefused::awardedByTooLong();
        }

        $this->awardedBy = $awardedBy;
    }

    public function setYear(?int $year, \DateTimeImmutable $now): void
    {
        if (null !== $year && !AwardPolicy::isCredibleYear($year, $now)) {
            throw AwardRefused::implausibleYear();
        }

        $this->year = $year;
    }

    public function setNote(?string $note): void
    {
        $note = self::trimmedOrNull($note);

        if (null !== $note && mb_strlen($note) > AwardPolicy::NOTE_MAX_LENGTH) {
            throw AwardRefused::noteTooLong();
        }

        $this->note = $note;
    }

    /**
     * `RN-6`. Solo `http` y `https`, con la misma comprobación que el enlace
     * de compra y las referencias de la página de autor: la escribe un
     * usuario y la pulsa cualquiera que abra su perfil.
     */
    public function setUrl(?string $url): void
    {
        $url = self::trimmedOrNull($url);

        if (null === $url) {
            $this->url = null;

            return;
        }

        if (mb_strlen($url) > AwardPolicy::URL_MAX_LENGTH || !WebAddress::isSafe($url)) {
            throw AwardRefused::invalidUrl();
        }

        $this->url = $url;
    }

    private static function acceptableTitle(string $title): string
    {
        $title = trim($title);

        if ('' === $title) {
            throw AwardRefused::missingTitle();
        }

        if (mb_strlen($title) > AwardPolicy::TITLE_MAX_LENGTH) {
            throw AwardRefused::titleTooLong();
        }

        return $title;
    }

    private static function trimmedOrNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
