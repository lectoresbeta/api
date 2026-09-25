<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Architecture;

use LectoresBeta\User\Preferences\Domain\Enum\ContentWarning as ReaderExcludes;
use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning as AuthorDeclares;
use PHPUnit\Framework\TestCase;

/**
 * Las dos mitades del contenido sensible: el autor declara qué hay
 * (`FEAT-WRK-017`) y el lector decide qué no quiere ver (`FEAT-USR-043`).
 *
 * Cada contexto tiene **su propia** enumeración, y eso es deliberado
 * (`AGENTS.md`): compartirla ataría el filtro del lector a la declaración del
 * autor. Lo que no puede pasar es que **diverjan**, porque una divergencia no
 * rompe nada visiblemente — simplemente deja una etiqueta que se puede
 * declarar y no se puede excluir, o al revés, y nadie se entera hasta que
 * alguien ve lo que pidió no ver.
 *
 * De ahí esta prueba: la duplicación se permite, la divergencia no. Es el
 * único sitio del proyecto donde las dos se miran a la vez, y por eso vive
 * aquí y no en producción.
 */
final class ContentWarningCatalogueTest extends TestCase
{
    public function testTheReaderCanExcludeExactlyWhatAnAuthorCanDeclare(): void
    {
        $declarables = array_map(
            static fn (AuthorDeclares $warning): string => $warning->value,
            AuthorDeclares::cases(),
        );

        $excludables = array_map(
            static fn (ReaderExcludes $warning): string => $warning->value,
            ReaderExcludes::cases(),
        );

        sort($declarables);
        sort($excludables);

        self::assertSame($declarables, $excludables);
    }
}
