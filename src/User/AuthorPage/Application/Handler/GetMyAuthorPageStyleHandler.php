<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Application\DTO\AuthorPageStyleView;
use LectoresBeta\User\AuthorPage\Application\Query\GetMyAuthorPageStyle;
use LectoresBeta\User\AuthorPage\Application\Service\StoredAuthorPageStyle;

/**
 * El estilo de quien pregunta (`FEAT-USR-016`).
 *
 * Quien nunca lo ha tocado recibe `CLASSIC` y `SLATE`, no un hueco: la
 * pantalla de edición tiene que poder marcar la opción activa el primer día.
 */
final readonly class GetMyAuthorPageStyleHandler
{
    public function __construct(private StoredAuthorPageStyle $stored)
    {
    }

    public function __invoke(GetMyAuthorPageStyle $query): AuthorPageStyleView
    {
        $style = $this->stored->of(UserId::fromString($query->userId));

        return new AuthorPageStyleView($style->theme(), $style->accentColour(), $style->updatedAt());
    }
}
