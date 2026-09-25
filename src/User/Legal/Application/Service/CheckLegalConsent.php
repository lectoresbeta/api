<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;
use LectoresBeta\User\Legal\Domain\Exception\LegalVersionOutdated;
use LectoresBeta\User\Legal\Domain\Exception\NoLegalDocumentsPublished;
use LectoresBeta\User\Legal\Domain\Repository\LegalDocumentRepository;

/**
 * Que lo aceptado sea **lo que de verdad rige hoy** (`FEAT-USR-024` `RN-2`).
 *
 * Guardar un booleano no demuestra nada si el texto cambia después, y guardar
 * una versión cualquiera tampoco: si alguien acepta `2020-01-01` y el
 * documento vigente es otro, lo que queda en la base de datos es una
 * constancia que **parece** una prueba y no lo es.
 *
 * Los dos documentos se comprueban por separado (`RN-5`) aunque la casilla
 * sea una sola: cambian por motivos distintos y con frecuencias distintas.
 *
 * Se usa en todos los caminos de alta (`RN-7`). Un proveedor externo acredita
 * quién es alguien, no qué ha aceptado.
 */
final readonly class CheckLegalConsent
{
    /**
     * Los que hay que aceptar para crear una cuenta. Los otros dos tipos del
     * catálogo —cookies y aviso legal— se publican, pero no se marcan en el
     * formulario de alta.
     */
    private const REQUIRED = [
        LegalDocumentType::TERMS_OF_USE,
        LegalDocumentType::PRIVACY_POLICY,
    ];

    public function __construct(
        private LegalDocumentRepository $documents,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, string> $accepted versión aceptada por tipo
     *
     * @return array<string, string> lo aceptado, ya comprobado
     */
    public function accepting(array $accepted): array
    {
        $now = $this->clock->now();
        $inForce = [];

        foreach (self::REQUIRED as $type) {
            $document = $this->documents->inForce($type, $now);

            if (null === $document) {
                throw NoLegalDocumentsPublished::create();
            }

            $inForce[$type->value] = $document->version();
        }

        foreach ($inForce as $type => $version) {
            if (($accepted[$type] ?? null) !== $version) {
                throw LegalVersionOutdated::insteadOf($inForce);
            }
        }

        return $inForce;
    }
}
