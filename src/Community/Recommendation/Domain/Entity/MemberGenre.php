<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Un género que le interesa a alguien, proyectado desde `User`
 * (`FEAT-COM-016` `RN-1`).
 *
 * **`Community` no consulta la tabla de géneros de `User`**: recibe el hecho
 * `LiteraryPreferencesUpdated` y mantiene su propia copia. Es la frontera
 * entre contextos, y el precio de tenerla es esta tabla: sin ella, sugerir
 * autores por afinidad exigiría leer por dentro de otro contexto en cada
 * carga del onboarding.
 */
class MemberGenre
{
    private string $memberId;

    private string $genreCode;

    public function __construct(MemberId $memberId, string $genreCode)
    {
        $this->memberId = $memberId->value();
        $this->genreCode = strtoupper($genreCode);
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }

    public function genreCode(): string
    {
        return $this->genreCode;
    }
}
