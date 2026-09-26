<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Un filtro con un valor que no existe (`FEAT-WRK-012`).
 *
 * **No se ignora**: devolver los resultados de otra consulta es peor que
 * devolver un error, porque el usuario no tiene forma de notarlo.
 *
 * `status=DRAFT` cae aquí, y es el caso que importa. Un desplegable que no
 * ofrece un valor no es una autorización: el borrador de otra persona no
 * aparece **aunque se fuerce el parámetro**.
 */
final class UnknownCatalogueFilter extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function status(): self
    {
        return new self(
            'UNKNOWN_FILTER_VALUE',
            'The status filter only takes PUBLISHED or IN_CORRECTION.',
        );
    }

    public static function sort(): self
    {
        return new self(
            'UNKNOWN_FILTER_VALUE',
            'The catalogue sorts by relevance or by recent.',
        );
    }

    /**
     * Una etiqueta de contenido inventada **no se ignora**, al revés que una
     * temática desconocida. Ahí no había forma de distinguir un código falso
     * de uno retirado del catálogo; aquí la lista es cerrada, así que un
     * valor que no está es una errata. Y el error va en la dirección
     * peligrosa: ignorarlo enseñaría justo lo que el lector ha pedido no ver.
     */
    public static function contentWarning(): self
    {
        return new self(
            'UNKNOWN_FILTER_VALUE',
            'That content warning does not exist; GET /content-warnings lists them.',
        );
    }

    public static function page(): self
    {
        return new self(
            'INVALID_PAGE',
            'A page number starts at one.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
