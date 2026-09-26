<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/content-warnings` (`FEAT-WRK-017`).
 *
 * Público, como el catálogo de temáticas: no es información sensible y hace
 * falta en pantallas que se pueden ver sin sesión.
 *
 * Devuelve **códigos y no nombres**, al revés que `GET /genres`. La
 * diferencia no es un descuido: las temáticas son datos curados en la base de
 * datos y su nombre puede cambiar sin tocar código, mientras que las
 * etiquetas de contenido son un catálogo cerrado de cinco valores
 * (`RN-4`). Cómo se le dice «SELF_HARM» a una persona —y la ficha pide que se
 * le diga con claridad, no con un icono ambiguo— es una decisión de la
 * interfaz, y escribirla aquí la congelaría en un idioma.
 *
 * Existe para que nadie tenga que copiar la lista en el cliente: es cerrada,
 * pero puede crecer.
 */
#[AsController]
final readonly class ListContentWarningsController
{
    public function __invoke(): Response
    {
        return new JsonResponse([
            'contentWarnings' => array_map(
                static fn (ContentWarning $warning): string => $warning->value,
                ContentWarning::cases(),
            ),
        ]);
    }
}
