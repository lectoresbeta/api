<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AuthorLinkId;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AuthorLinkLabel;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AuthorLinkUrl;

/**
 * Una referencia de la página de autor (`FEAT-USR-015`).
 *
 * **La página de autor es el perfil**, no una pantalla aparte
 * (`P-5`, resuelta): el nombre, la biografía, la foto y la portada ya viven
 * en `User\Profile`, y las obras publicadas en este mismo concepto. Lo único
 * que faltaba eran las referencias, y es lo que esta entidad añade.
 *
 * Etiqueta y dirección, y las dos hacen falta. Una dirección desnuda no dice
 * a dónde lleva, y un enlace que no dice a dónde lleva es el que nadie pulsa
 * — o el que se pulsa por error.
 */
class AuthorLink
{
    private string $id;

    private string $userId;

    private string $label;

    private string $url;

    /**
     * En qué orden los puso su dueño. Es suyo y no se ordena por fecha:
     * quien pone su web primero y su cuenta de fotos después lo ha decidido.
     */
    private int $position;

    public function __construct(
        AuthorLinkId $id,
        UserId $userId,
        AuthorLinkLabel $label,
        AuthorLinkUrl $url,
        int $position,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->label = $label->value();
        $this->url = $url->value();
        $this->position = $position;
    }

    public function id(): AuthorLinkId
    {
        return AuthorLinkId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function position(): int
    {
        return $this->position;
    }
}
