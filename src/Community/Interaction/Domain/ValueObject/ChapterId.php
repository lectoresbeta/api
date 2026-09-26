<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * Un identificador de otro contexto, guardado como valor. `Community` no lee
 * las tablas de `Work`: pregunta por contrato quién puede ver el capítulo y
 * guarda al lado lo suyo.
 */
final readonly class ChapterId extends Uuid
{
}
