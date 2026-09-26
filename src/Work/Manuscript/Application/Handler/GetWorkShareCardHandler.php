<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Sharing\CanonicalUrl;
use LectoresBeta\Shared\Application\Sharing\ShareCard;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkCards;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;

/**
 * La tarjeta con la que se comparte una obra fuera (`FEAT-WRK-011`).
 *
 * **No genera ningún enlace.** El enlace es la dirección canónica de la obra,
 * la de siempre; lo que esto añade son los metadatos con los que WhatsApp o
 * Twitter pintan la previsualización. Nada que caduque, nada que revocar,
 * nada que se filtre.
 *
 * **Solo de obras visibles para cualquiera**, y eso no se comprueba aquí: se
 * pregunta a `WorkCards`, que ya responde solo por esas. Un borrador, una
 * obra archivada o una bloqueada por moderación no están, así que compartir
 * no puede ser una puerta trasera a lo que la visibilidad cierra.
 *
 * Responde lo mismo para una obra que no existe y para una que no se puede
 * enseñar: decir cuál de las dos es contaría que existe una obra inédita.
 */
final readonly class GetWorkShareCardHandler
{
    /** Lo que cabe en una previsualización antes de que la corte el cliente. */
    private const DESCRIPTION = 200;

    public function __construct(
        private WorkCards $works,
        private string $shareUrlTemplate,
    ) {
    }

    public function __invoke(string $workId): ShareCard
    {
        $work = $this->works->ofWorks([$workId])[$workId] ?? throw WorkNotFound::create();

        return new ShareCard(
            CanonicalUrl::from($this->shareUrlTemplate, $work->workId),
            $work->title,
            self::preview($work->synopsis),
            // Una obra todavía no tiene portada en el modelo. Cuando la
            // tenga, entra aquí y la previsualización deja de ser solo texto.
            null,
        );
    }

    private static function preview(?string $synopsis): ?string
    {
        if (null === $synopsis) {
            return null;
        }

        return mb_strlen($synopsis) <= self::DESCRIPTION
            ? $synopsis
            : rtrim(mb_substr($synopsis, 0, self::DESCRIPTION)).'…';
    }
}
